<?php

namespace App\Services;

use App\Http\Filters\AccountFilter;
use App\Models\Account;
use Carbon\Carbon;
use Exception;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;

class AccountService
{
    private const ERROR_MESSAGES = [
        'createError' => 'Произошла ошибка во время создания счета. Проверьте переданные вами поля'
    ];

    /**
     * @param AccountFilter $filter
     * @param array $queryParams
     * @return LengthAwarePaginator
     */
    public function getList(AccountFilter $filter, array $queryParams = []): LengthAwarePaginator
    {
        $limit = $queryParams["limit"] ?? config("constants.paginator.limit");

        return Account::filter($filter)->paginate($limit);
    }

    /**
     * @param array
     * @return Account
     * @throws Exception
     */
    public function create(array $data): Account
    {
        try {
            $user = Auth::user();

            $dataInvoiceDate = $data['invoice_date'] ?? date('Y-m-d');

            $invoiceDate = Carbon::parse($dataInvoiceDate)->format('Y-m-d');

            return Account::create([
                "deal_id" => $data["deal_id"],
                "author_id" => $data["author_id"] ?? $user->id,
                "sum" => $data["sum"] ?? 0,
                "invoice_date" => $invoiceDate
            ]);
        } catch (\Throwable $e) {
            throw new Exception($this::ERROR_MESSAGES['createError']);
        }
    }

    /**
     * @param Account $account
     * @param array
     * @return Account
     */
    public function update(Account $account, array $data): Account
    {
        $account->update($data);

        return $account;
    }

    /**
     * @param Account $account
     * @return void
     */
    public function delete(Account $account): void
    {
        $account->delete();
    }
}
