import copy
import tempfile
import unittest
from pathlib import Path
from simulator import open_local, initialize, apply, rollback, backup_to, snapshot_hash, balances, validate_plan, encode


def fixture():
    before = {'id': 'existing', 'user_id': 'u', 'amount': '20.00', 'deleted_at': None}
    after = dict(before, economic_kind='expense')
    p = {'batch_id': 'b1', 'mode': 'offline-staging-only', 'user_id': 'u', 'internal_pairs': [],
         'sources': [{'key': 'bank:1', 'ready': True, 'cash': {'account_id': 'a', 'date': '2026-10-13', 'cents': -2000}}],
         'proposals': [{'id': 'p1', 'source_keys': ['bank:1'], 'action': 'update',
                        'targets': [{'id': 'existing', 'before': before, 'after': after}],
                        'event': {'kind': 'expense', 'income_cents': 0, 'expense_cents': 2000}}]}
    return {'transactions': [before]}, p


class SimulatorTests(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory()
        self.db = open_local(Path(self.tmp.name)/'test.sqlite')
        self.snapshot, self.plan = fixture()
        initialize(self.db, self.snapshot)

    def tearDown(self):
        self.db.close()
        self.tmp.cleanup()

    def test_repeat_import_has_zero_writes_and_no_second_cash_effect(self):
        self.assertEqual(apply(self.db, self.plan)['updated'], 1)
        first = snapshot_hash(self.db)
        self.assertEqual(apply(self.db, self.plan), {'inserted': 0, 'updated': 0, 'replayed': True})
        self.assertEqual(snapshot_hash(self.db), first)
        self.assertEqual(balances(self.db, {'a': 5000}), {'a': 3000})

    def test_same_batch_with_changed_content_is_rejected(self):
        apply(self.db, self.plan)
        self.plan['proposals'][0]['event']['expense_cents'] = 1900
        with self.assertRaises(ValueError): apply(self.db, self.plan)

    def test_cross_batch_duplicate_source_is_atomic(self):
        apply(self.db, self.plan)
        self.plan['batch_id'] = 'b2'
        with self.assertRaises(Exception): apply(self.db, self.plan)
        self.assertEqual(self.db.execute('SELECT count(*) FROM batches').fetchone()[0], 1)

    def test_stale_precondition_leaves_no_partial_rows(self):
        self.plan['proposals'][0]['targets'][0]['before']['amount'] = '99.00'
        with self.assertRaises(ValueError): apply(self.db, self.plan)
        self.assertEqual(self.db.execute('SELECT count(*) FROM evidence').fetchone()[0], 0)
        self.assertEqual(self.db.execute('SELECT count(*) FROM batches').fetchone()[0], 0)

    def test_rollback_restores_original_and_keeps_journal(self):
        old = snapshot_hash(self.db)
        apply(self.db, self.plan)
        rollback(self.db, 'b1')
        self.assertEqual(snapshot_hash(self.db), old)
        self.assertEqual(self.db.execute('SELECT count(*) FROM cash').fetchone()[0], 0)
        self.assertEqual(self.db.execute('SELECT count(*) FROM changes').fetchone()[0], 1)

    def test_rollback_refuses_to_overwrite_later_edit(self):
        apply(self.db, self.plan)
        with self.db: self.db.execute("UPDATE records SET payload='{}' WHERE record_id='existing'")
        with self.assertRaises(ValueError): rollback(self.db, 'b1')
        self.assertEqual(self.db.execute('SELECT state FROM batches').fetchone()[0], 'applied')

    def test_backup_is_restorable(self):
        backup = Path(self.tmp.name)/'backup.sqlite'
        backup_to(self.db, backup)
        other = open_local(backup)
        try: self.assertEqual(snapshot_hash(other), snapshot_hash(self.db))
        finally: other.close()

    def test_cross_user_change_is_rejected(self):
        self.plan['proposals'][0]['targets'][0]['after']['user_id'] = 'other'
        with self.assertRaises(ValueError): validate_plan(self.plan)
        _, self.plan = fixture()
        self.plan['proposals'][0]['targets'][0]['before']['user_id'] = 'other'
        with self.assertRaises(ValueError): validate_plan(self.plan)
        _, self.plan = fixture()
        self.plan['proposals'][0]['targets'][0]['after']['id'] = 'different-record'
        with self.assertRaises(ValueError): validate_plan(self.plan)

    def test_deleted_record_cannot_be_restored(self):
        self.plan['proposals'][0]['targets'][0]['before']['deleted_at'] = '2026-01-01'
        with self.assertRaises(ValueError): validate_plan(self.plan)

    def test_reimbursement_cannot_inflate_income(self):
        event = self.plan['proposals'][0]['event']
        event.update(kind='reimbursement', income_cents=2075, expense_cents=0)
        with self.assertRaises(ValueError): validate_plan(self.plan)

    def test_unbalanced_internal_transfer_is_rejected(self):
        self.plan['internal_pairs'] = [{'keys': ['bank:1']}]
        with self.assertRaises(ValueError): validate_plan(self.plan)

    def test_noninteger_money_is_rejected(self):
        self.plan['sources'][0]['cash']['cents'] = -20.0
        with self.assertRaises(ValueError): validate_plan(self.plan)

    def test_two_partial_payments_with_distinct_sources_are_not_deduplicated(self):
        self.plan['proposals'] = []
        self.plan['sources'] = []
        for i in range(2):
            key = f'bank:{i}'
            self.plan['sources'].append({'key': key, 'ready': True, 'cash': {'account_id': 'a', 'date': '2026-10-13', 'cents': -1000}})
            self.plan['proposals'].append({'id': f'pay{i}', 'source_keys': [key], 'action': 'insert',
                'targets': [{'id': f'pay{i}', 'before': None, 'after': {'id': f'pay{i}', 'user_id': 'u', 'statement_month': '2026-10'}}],
                'event': {'kind': 'card_payment', 'income_cents': 0, 'expense_cents': 0}})
        self.assertEqual(apply(self.db, self.plan)['inserted'], 2)
        self.assertEqual(balances(self.db, {'a': 5000}), {'a': 3000})

    def test_documentary_cash_and_blocked_matching_are_distinct(self):
        self.plan['sources'][0]['ready'] = False
        self.plan['proposals'][0]['action'] = 'review'
        self.assertEqual(apply(self.db, self.plan)['updated'], 0)
        self.assertEqual(balances(self.db, {'a': 5000}), {'a': 3000})
        self.assertEqual(balances(self.db, {'a': 5000}, ready_only=True), {'a': 5000})


if __name__ == '__main__': unittest.main()
