<?php

namespace App\Domains\BankSync\Services;

/** Bank names by COMPE code: MeuPluggy names every connection "MeuPluggy", the account's code names the bank. */
final class BankInstitutions
{
    private const NAMES = [
        '001' => 'Banco do Brasil', '033' => 'Santander', '041' => 'Banrisul', '070' => 'BRB', '077' => 'Inter',
        '102' => 'XP', '104' => 'Caixa', '208' => 'BTG Pactual', '212' => 'Banco Original', '237' => 'Bradesco',
        '260' => 'Nubank', '290' => 'PagBank', '323' => 'Mercado Pago', '336' => 'C6 Bank', '341' => 'Itaú',
        '380' => 'PicPay', '403' => 'Cora', '422' => 'Safra', '461' => 'Asaas', '536' => 'Neon', '655' => 'Neon',
        '748' => 'Sicredi', '756' => 'Sicoob',
    ];

    /** "260/0001/1234567-8" → "260" */
    public static function codeFromTransferNumber(?string $transferNumber): ?string
    {
        if (! is_string($transferNumber) || ! preg_match('/^\s*(\d{1,4})\s*\//', $transferNumber, $m)) {
            return null;
        }

        return str_pad(ltrim($m[1], '0') ?: '0', 3, '0', STR_PAD_LEFT);
    }

    public static function name(?string $code): ?string
    {
        return $code === null ? null : (self::NAMES[$code] ?? null);
    }
}
