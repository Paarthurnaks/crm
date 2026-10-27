<?php

namespace App\Services;

use App\Http\Filters\AccountFilter;
use App\Models\Account;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Pagination\LengthAwarePaginator;

class AccountService
{
    private const ERROR_MESSAGES = [
        'createError' => 'Произошла ошибка во время создания счета. Проверьте переданные вами поля',
    ];

    public function getList(AccountFilter $filter, array $queryParams = []): LengthAwarePaginator
    {
        $limit = $queryParams['limit'] ?? config('constants.paginator.limit');

        return Account::filter($filter)->paginate($limit);
    }

    /**
     * @param array
     *
     * @throws Exception
     */
    public function create(array $data, User $actor): Account
    {
        try {
            $dataInvoiceDate = $data['invoice_date'] ?? now()->toDateString();

            $invoiceDate = Carbon::parse($dataInvoiceDate)->format('Y-m-d');

            return Account::create([
                'deal_id' => $data['deal_id'],
                'author_id' => $data['author_id'] ?? $actor->id,
                'sum' => $data['sum'] ?? 0,
                'invoice_date' => $invoiceDate,
            ]);
        } catch (\Throwable $e) {
            throw new Exception($this::ERROR_MESSAGES['createError'], 0, $e);
        }
    }

    /**
     * @param array
     */
    public function update(Account $account, array $data): Account
    {
        $account->update($data);

        return $account;
    }

    public function delete(Account $account): void
    {
        $account->delete();
    }
}
