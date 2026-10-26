<?php

namespace NYCorp\Finance\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use NYCorp\Finance\Exceptions\FinanceException;
use NYCorp\Finance\Http\Response\ResponseCode;

/**
 * @property int $id
 * @property string $finance_transaction_id
 * @property string $tag
 * @property Carbon|null $created_at
 */
class FinanceTransactionTag extends Model
{
    public const ID = 'id';
    public const FINANCE_TRANSACTION_ID = 'finance_transaction_id';
    public const TAG = 'tag';
    public const CREATED_AT = 'created_at';
    public const UPDATED_AT = null;

    public const MAX_LENGTH = 191;

    protected $table = 'finance_transaction_tags';

    protected $guarded = [self::ID];

    /**
     * Trimmed, unique, non-empty tags.
     *
     * @throws FinanceException on a tag that is not a string or is too long
     */
    public static function normalize(array|string|null $tags): array
    {
        $normalized = [];
        foreach ((array)$tags as $tag) {
            if (!is_string($tag) && !is_int($tag)) {
                throw new FinanceException(ResponseCode::REQUEST_VALIDATION_ERROR, 'A tag must be a string');
            }
            $tag = trim((string)$tag);
            if ($tag === '') {
                continue;
            }
            if (mb_strlen($tag) > self::MAX_LENGTH) {
                throw new FinanceException(ResponseCode::REQUEST_VALIDATION_ERROR, 'A tag cannot exceed ' . self::MAX_LENGTH . ' characters');
            }
            $normalized[$tag] = $tag;
        }
        return array_values($normalized);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(FinanceTransaction::class, self::FINANCE_TRANSACTION_ID);
    }
}
