<?php

namespace App\Observers;

use App\Models\Lead;
use App\Models\Pembayaran;
use App\Models\TargetKinerjaUser;
use App\Support\CrmNotifier;
use Illuminate\Database\Eloquent\Model;

class CrmNotificationObserver
{
    public function created(Model $model): void
    {
        $notifier = app(CrmNotifier::class);
        if ($model instanceof Lead) $notifier->leadAssigned($model);
        if ($model instanceof TargetKinerjaUser) $notifier->targetAssigned($model);
        if ($model instanceof Pembayaran) $notifier->pendingPayment($model);
    }

    public function updated(Model $model): void
    {
        if ($model instanceof Lead && $model->wasChanged('diserahkan_ke_user_id')) {
            app(CrmNotifier::class)->leadAssigned($model);
        }
    }
}
