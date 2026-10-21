<?php

namespace App\Enums;

/** One to one with client/lib/features/inventory/domain/movement_type_meta.dart. */
enum MovementType: string
{
    case OpeningStock = 'opening_stock';
    case Purchase = 'purchase';
    case BulkImport = 'bulk_import';
    case Sale = 'sale';
    case Refund = 'refund';
    case ExchangeReturn = 'exchange_return';
    case ExchangeSale = 'exchange_sale';
    case Damage = 'damage';
    case Adjustment = 'adjustment';
    case TransferIn = 'transfer_in';
    case TransferOut = 'transfer_out';

    /** +1 (stock comes back in) or -1 (stock leaves) per the ledger (§13). */
    public function direction(): int
    {
        return match ($this) {
            self::Refund, self::ExchangeReturn, self::OpeningStock,
            self::Purchase, self::BulkImport, self::TransferIn => 1,
            self::Sale, self::ExchangeSale, self::Damage,
            self::Adjustment, self::TransferOut => -1,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::OpeningStock => 'Opening stock',
            self::Purchase => 'Purchase',
            self::BulkImport => 'Bulk import',
            self::Sale => 'Sale',
            self::Refund => 'Refund',
            self::ExchangeReturn => 'Exchange return',
            self::ExchangeSale => 'Exchange sale',
            self::Damage => 'Damage',
            self::Adjustment => 'Adjustment',
            self::TransferIn => 'Transfer in',
            self::TransferOut => 'Transfer out',
        };
    }
}
