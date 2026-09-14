<?php

declare(strict_types=1);

namespace App\Filament\Resources\Transactions\Pages;

use App\Filament\Resources\Transactions\TransactionResource;
use App\Models\Transaction;
use App\Services\AutoCategoriser;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTransaction extends EditRecord
{
    protected static string $resource = TransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }

    /** Every manual correction is a training signal for the categoriser. */
    protected function afterSave(): void
    {
        /** @var Transaction $record */
        $record = $this->getRecord();

        app(AutoCategoriser::class)->learnFrom($record);
    }
}
