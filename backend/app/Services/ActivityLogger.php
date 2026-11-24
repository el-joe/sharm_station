<?php

namespace App\Services;

use App\DTOs\Activity\ActivityFilterData;
use App\Enums\ActivityAction;
use App\Models\ActivityLog;
use App\Models\User;
use App\Repositories\Contracts\ActivityLogRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Activity log (SRS 14.1): records what back-office users do. Actions by customers, partners,
 * the system (commands, webhooks) and seeders are not logged unless an actor is given.
 */
class ActivityLogger
{
    /**
     * Attributes never written to the log.
     *
     * @var list<string>
     */
    public const IGNORED_ATTRIBUTES = ['created_at', 'updated_at', 'password', 'remember_token', 'access_token', 'token'];

    public function __construct(private readonly ActivityLogRepositoryInterface $logs) {}

    /**
     * @param  array<string, mixed>  $properties
     */
    public function log(ActivityAction $action, ?Model $subject, string $description, array $properties = [], ?User $causer = null): ?ActivityLog
    {
        $causer ??= $this->currentStaff();

        if ($causer === null) {
            return null;
        }

        $request = app()->runningInConsole() && ! app()->runningUnitTests() ? null : request();

        return $this->logs->create([
            'causer_id' => $causer->id,
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'description' => Str::limit($description, 250),
            'properties' => $properties === [] ? null : $properties,
            'ip_address' => $request?->ip(),
            'user_agent' => $request !== null ? Str::limit((string) $request->userAgent(), 490, '') : null,
            'created_at' => now(),
        ]);
    }

    /**
     * Generic create/update/delete of a model by a signed-in staff member, with the changed values.
     */
    public function modelEvent(ActivityAction $action, Model $model): void
    {
        if ($this->currentStaff() === null) {
            return;
        }

        $properties = [];

        if ($action === ActivityAction::Updated) {
            $changes = array_diff_key($model->getChanges(), array_flip([...self::IGNORED_ATTRIBUTES, ...$model->getHidden()]));

            if ($changes === []) {
                return;
            }

            $properties = ['changes' => [
                'old' => array_intersect_key($model->getRawOriginal(), $changes),
                'new' => array_intersect_key($model->getAttributes(), $changes),
            ]];
        } elseif ($action === ActivityAction::Created) {
            $properties = ['attributes' => array_diff_key($model->getAttributes(), array_flip([...self::IGNORED_ATTRIBUTES, ...$model->getHidden()]))];
        }

        $this->log($action, $model, $this->subjectName($model).' '.$action->value, $properties);
    }

    /**
     * Staff member signed into the admin panel, if any.
     */
    public function currentStaff(): ?User
    {
        $user = Auth::guard('web')->user();

        return $user instanceof User && $user->isStaff() ? $user : null;
    }

    /**
     * Human label of a subject, e.g. Trip “Desert safari”.
     */
    public function subjectName(Model $model): string
    {
        $label = null;

        foreach (['reference', 'code', 'title', 'name', 'company', 'key', 'slug', 'email'] as $attribute) {
            $value = $model->getAttribute($attribute);

            if (is_array($value)) {
                $value = $value['en'] ?? reset($value);
            }

            if (is_scalar($value) && $value !== '') {
                $label = (string) $value;
                break;
            }
        }

        return Str::headline($model->getMorphClass()).($label !== null ? ' “'.Str::limit($label, 80).'”' : ' #'.$model->getKey());
    }

    /**
     * @return Builder<ActivityLog>
     */
    public function adminQuery(ActivityFilterData $filters): Builder
    {
        return $this->logs->adminQuery($filters);
    }

    /**
     * @return array<string, string>
     */
    public function subjectTypeOptions(): array
    {
        return collect($this->logs->subjectTypes())->mapWithKeys(fn (string $type): array => [$type => Str::headline($type)])->all();
    }
}
