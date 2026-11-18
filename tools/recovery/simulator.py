"""Offline recovery rehearsal. SQLite only; no Laravel bootstrap or network support.

This is a staging model, not an importer for the application database. Monetary
values are integer cents. The supplied plan retains originals and proposed deltas.
"""
from pathlib import Path
import hashlib
import json
import sqlite3


def encode(value):
    return json.dumps(value, ensure_ascii=False, sort_keys=True, separators=(',', ':'))


def digest(value):
    return hashlib.sha256(encode(value).encode()).hexdigest()


def open_local(path):
    path = Path(path).resolve()
    if path.suffix != '.sqlite' or str(path).startswith(('\\\\', '//')):
        raise ValueError('Only a local .sqlite file is supported')
    path.parent.mkdir(parents=True, exist_ok=True)
    db = sqlite3.connect(path)
    db.execute('PRAGMA foreign_keys=ON')
    return db


def initialize(db, snapshot):
    if db.execute("SELECT count(*) FROM sqlite_master WHERE type='table'").fetchone()[0]:
        raise ValueError('Refusing to initialize an existing database')
    db.executescript('''
      CREATE TABLE originals(table_name TEXT NOT NULL, record_id TEXT NOT NULL,
        payload TEXT NOT NULL, PRIMARY KEY(table_name,record_id));
      CREATE TABLE records(record_id TEXT PRIMARY KEY, payload TEXT NOT NULL);
      CREATE TABLE batches(id TEXT PRIMARY KEY, plan_hash TEXT NOT NULL, state TEXT NOT NULL);
      CREATE TABLE proposals(batch_id TEXT NOT NULL REFERENCES batches(id), proposal_id TEXT NOT NULL,
        payload TEXT NOT NULL, PRIMARY KEY(batch_id,proposal_id));
      CREATE TABLE evidence(source_key TEXT PRIMARY KEY, batch_id TEXT NOT NULL REFERENCES batches(id),
        payload TEXT NOT NULL);
      CREATE TABLE cash(source_key TEXT PRIMARY KEY REFERENCES evidence(source_key),
        account_id TEXT NOT NULL, posted_on TEXT NOT NULL, cents INTEGER NOT NULL,
        ready INTEGER NOT NULL CHECK(ready IN(0,1)));
      CREATE TABLE events(id TEXT PRIMARY KEY, batch_id TEXT NOT NULL REFERENCES batches(id), payload TEXT NOT NULL);
      CREATE TABLE changes(batch_id TEXT NOT NULL REFERENCES batches(id),record_id TEXT NOT NULL,
        before_json TEXT,after_json TEXT NOT NULL,PRIMARY KEY(batch_id,record_id));
    ''')
    with db:
        for table, rows in snapshot.items():
            if not isinstance(rows, list):
                continue
            for index, row in enumerate(rows):
                rid = row.get('id', str(index))
                db.execute('INSERT INTO originals VALUES (?,?,?)', (table, rid, encode(row)))
                if table == 'transactions':
                    db.execute('INSERT INTO records VALUES (?,?)', (rid, encode(row)))


def validate_plan(plan):
    if plan['mode'] != 'offline-staging-only':
        raise ValueError('Unsupported execution target')
    ids = [x['id'] for x in plan['proposals']]
    if len(ids) != len(set(ids)):
        raise ValueError('Duplicate proposal identity')
    source_ids = [x['key'] for x in plan['sources']]
    if len(source_ids) != len(set(source_ids)):
        raise ValueError('Duplicate source identity')
    targets = []
    for p in plan['proposals']:
        if not set(p['source_keys']).issubset(source_ids):
            raise ValueError('Unresolved source reference')
        if p['action'] in ('insert', 'update'):
            for target in p['targets']:
                if target['after']['user_id'] != plan['user_id'] or (target['before'] and target['before']['user_id'] != plan['user_id']):
                    raise ValueError('Cross-user mutation')
                if target['id'] != target['after']['id'] or (target['before'] and target['id'] != target['before']['id']):
                    raise ValueError('Mismatched target identity')
                if target['before'] and target['before'].get('deleted_at'):
                    raise ValueError('Restoration of deleted records is prohibited')
                targets.append(target['id'])
        event = p['event']
        if event['kind'] in ('internal_transfer', 'card_payment', 'reimbursement', 'historical_debt'):
            if event['income_cents'] or event['expense_cents']:
                raise ValueError('Transfer/debt/reimbursement counted as income or spending')
    if len(targets) != len(set(targets)):
        raise ValueError('Conflicting proposals target the same record')
    for src in plan['sources']:
        if src.get('cash') and (type(src['cash']['cents']) is not int):
            raise ValueError('Cash amounts must be integer cents')
    for pair in plan['internal_pairs']:
        legs = [s['cash'] for s in plan['sources'] if s['key'] in pair['keys']]
        if len(legs) != 2 or sum(x['cents'] for x in legs) != 0:
            raise ValueError('Unbalanced transfer')


def apply(db, plan):
    validate_plan(plan)
    ph = digest(plan)
    prior = db.execute('SELECT plan_hash,state FROM batches WHERE id=?', (plan['batch_id'],)).fetchone()
    if prior:
        if prior != (ph, 'applied'):
            raise ValueError('Batch collision or rolled-back batch; use a new reviewed batch')
        return {'inserted': 0, 'updated': 0, 'replayed': True}
    result = {'inserted': 0, 'updated': 0, 'replayed': False}
    with db:
        db.execute('INSERT INTO batches VALUES (?,?,?)', (plan['batch_id'], ph, 'applied'))
        for src in plan['sources']:
            db.execute('INSERT INTO evidence VALUES (?,?,?)', (src['key'], plan['batch_id'], encode(src)))
            if src.get('cash'):
                c = src['cash']
                db.execute('INSERT INTO cash VALUES (?,?,?,?,?)', (src['key'], c['account_id'], c['date'], c['cents'], int(src['ready'])))
        for p in plan['proposals']:
            db.execute('INSERT INTO proposals VALUES (?,?,?)', (plan['batch_id'], p['id'], encode(p)))
            db.execute('INSERT INTO events VALUES (?,?,?)', (p['id'], plan['batch_id'], encode(p['event'])))
            if p['action'] not in ('insert', 'update'):
                continue
            for t in p['targets']:
                current = db.execute('SELECT payload FROM records WHERE record_id=?', (t['id'],)).fetchone()
                before = encode(t['before']) if t['before'] is not None else None
                if (current[0] if current else None) != before:
                    raise ValueError('Stale precondition: '+t['id'])
                after = encode(t['after'])
                db.execute('INSERT INTO changes VALUES (?,?,?,?)', (plan['batch_id'], t['id'], before, after))
                if current:
                    db.execute('UPDATE records SET payload=? WHERE record_id=?', (after, t['id']))
                    result['updated'] += 1
                else:
                    db.execute('INSERT INTO records VALUES (?,?)', (t['id'], after))
                    result['inserted'] += 1
    return result


def rollback(db, batch_id):
    batch = db.execute('SELECT state FROM batches WHERE id=?', (batch_id,)).fetchone()
    if not batch or batch[0] != 'applied':
        raise ValueError('No applied batch')
    changes = db.execute('SELECT record_id,before_json,after_json FROM changes WHERE batch_id=?', (batch_id,)).fetchall()
    with db:
        for rid, before, after in changes:
            current = db.execute('SELECT payload FROM records WHERE record_id=?', (rid,)).fetchone()
            if not current or current[0] != after:
                raise ValueError('Post-import edit prevents rollback: '+rid)
        for rid, before, _ in changes:
            if before is None:
                db.execute('DELETE FROM records WHERE record_id=?', (rid,))
            else:
                db.execute('UPDATE records SET payload=? WHERE record_id=?', (before, rid))
        db.execute('DELETE FROM cash WHERE source_key IN (SELECT source_key FROM evidence WHERE batch_id=?)', (batch_id,))
        db.execute('DELETE FROM evidence WHERE batch_id=?', (batch_id,))
        db.execute('DELETE FROM events WHERE batch_id=?', (batch_id,))
        db.execute('UPDATE batches SET state=? WHERE id=?', ('rolled_back', batch_id))
    # Proposals and before/after journal deliberately remain as an audit trail.


def snapshot_hash(db):
    return digest(db.execute('SELECT record_id,payload FROM records ORDER BY record_id').fetchall())


def backup_to(db, target):
    target = Path(target)
    if target.exists():
        raise ValueError('Refusing to overwrite backup')
    other = open_local(target)
    db.backup(other)
    assert other.execute('PRAGMA integrity_check').fetchone()[0] == 'ok'
    other.close()


def balances(db, openings, ready_only=False):
    result = {}
    for acc, opening in openings.items():
        delta = db.execute('SELECT COALESCE(sum(cents),0) FROM cash WHERE account_id=?'+(' AND ready=1' if ready_only else ''), (acc,)).fetchone()[0]
        result[acc] = opening + delta
    return result
