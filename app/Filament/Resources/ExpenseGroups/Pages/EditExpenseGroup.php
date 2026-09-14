<?php

declare(strict_types=1);

namespace App\Filament\Resources\ExpenseGroups\Pages;

use App\Filament\Resources\ExpenseGroups\ExpenseGroupResource;
use App\Models\ExpenseGroup;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditExpenseGroup extends EditRecord
{
    protected static string $resource = ExpenseGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('share')
                ->label('Copy share link')
                ->icon('heroicon-o-link')
                ->action(function (ExpenseGroup $record): void {
                    $record->enableSharing();

                    Notification::make()
                        ->title('Link ready')
                        ->body($record->shareUrl())
                        ->success()
                        ->persistent()
                        ->send();
                }),

            DeleteAction::make(),
        ];
    }
}
