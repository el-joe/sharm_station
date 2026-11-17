<?php

namespace App\Services;

use App\DTOs\Partner\CommissionMatrixData;
use App\DTOs\Partner\PartnerApplicationData;
use App\Enums\CommissionCategory;
use App\Enums\PartnerStatus;
use App\Enums\UserRole;
use App\Exceptions\DomainValidationException;
use App\Models\Partner;
use App\Models\User;
use App\Repositories\Contracts\PartnerRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Partner applications, approval and the commission override matrix (SRS 9).
 */
class PartnerService
{
    public function __construct(
        private readonly PartnerRepositoryInterface $partners,
        private readonly UserRepositoryInterface $users,
    ) {}

    /**
     * Creates a pending partner for the signed-in user, or for a new account.
     *
     * @return array{partner: Partner, user: User, token: string|null}
     */
    public function apply(PartnerApplicationData $data, ?User $user = null): array
    {
        return DB::transaction(function () use ($data, $user): array {
            $token = null;

            if ($user === null) {
                if ($data->email === null || $data->password === null || $data->name === null) {
                    throw DomainValidationException::withErrors(['email' => ['Name, email and password are required to apply.']]);
                }

                if ($this->users->findByEmail($data->email) !== null) {
                    throw DomainValidationException::withErrors(['email' => ['An account with this email already exists. Sign in to apply as a partner.']]);
                }

                $user = $this->users->create([
                    'name' => $data->name,
                    'email' => $data->email,
                    'password' => $data->password,
                    'phone' => $data->phone,
                    'role' => UserRole::Customer,
                ]);
                $token = $user->createToken('partner-application')->plainTextToken;
            }

            if ($this->partners->findByUserId($user->id) !== null) {
                throw DomainValidationException::withErrors(['company' => ['You have already applied as a partner.']]);
            }

            $partner = $this->partners->create([
                'user_id' => $user->id,
                'company' => $data->company,
                'tax_number' => $data->taxNumber,
                'country' => $data->country,
                'website' => $data->website,
                'phone' => $data->phone ?? $user->phone,
                'message' => $data->message,
                'status' => PartnerStatus::Pending,
            ]);

            return ['partner' => $partner, 'user' => $user, 'token' => $token];
        });
    }

    /**
     * Activates the partner; its commissions come from the global settings unless overridden on its profile.
     */
    public function approve(Partner $partner, User $approver): Partner
    {
        return DB::transaction(function () use ($partner, $approver): Partner {
            $this->partners->update($partner, [
                'status' => PartnerStatus::Active,
                'approved_at' => now(),
                'approved_by' => $approver->id,
            ]);

            $this->users->update($partner->user, ['role' => UserRole::Partner]);

            return $partner;
        });
    }

    public function suspend(Partner $partner): Partner
    {
        return $this->partners->update($partner, ['status' => PartnerStatus::Suspended]);
    }

    /**
     * @param  array<string, float|int|string|null>  $commissions  category value => percent override (null removes it)
     */
    public function setCommissions(Partner $partner, array $commissions): Partner
    {
        return DB::transaction(function () use ($partner, $commissions): Partner {
            foreach ($commissions as $category => $percent) {
                $this->partners->setCategoryCommission($partner, CommissionCategory::from($category), $percent === null || $percent === '' ? null : (float) $percent);
            }

            return $partner->load('categoryCommissions');
        });
    }

    /**
     * @return Builder<Partner>
     */
    public function adminQuery(?PartnerStatus $status = null): Builder
    {
        return $this->partners->adminQuery($status);
    }

    /**
     * @return Collection<int, Partner>
     */
    public function commissionMatrix(): Collection
    {
        return $this->partners->forCommissionMatrix();
    }

    /**
     * Saves every row of the commission matrix in one transaction.
     *
     * @throws DomainValidationException when a row references an unknown partner.
     */
    public function saveCommissionMatrix(CommissionMatrixData $matrix): int
    {
        return DB::transaction(function () use ($matrix): int {
            $partners = $this->partners->findMany(array_keys($matrix->rows))->keyBy('id');

            foreach ($matrix->rows as $partnerId => $row) {
                $partner = $partners->get($partnerId)
                    ?? throw DomainValidationException::withErrors(['partners' => ["Partner #{$partnerId} does not exist."]]);

                $this->setCommissions($partner, $row['categories']);
            }

            return count($matrix->rows);
        });
    }
}
