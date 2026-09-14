<?php

declare(strict_types=1);

namespace App\Filament\Resources\ExpenseGroups\Pages;

use App\Filament\Resources\ExpenseGroups\ExpenseGroupResource;
use App\Models\ExpenseGroup;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListExpenseGroups extends ListRecords
{
    protected static string $resource = ExpenseGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Start a trip')
                ->mutateFormDataUsing(function (array $data): array {
                    $data['owner_id'] = auth()->id();

                    return $data;
                })
                // The creator is always a member, otherwise they cannot be owed anything.
                ->after(function (ExpenseGroup $record): void {
                    $record->members()->firstOrCreate(
                        ['user_id' => auth()->id()],
                        [
                            'display_name' => auth()->user()->name,
                            'email' => auth()->user()->email,
                            'upi_vpa' => auth()->user()->upi_vpa,
                            'role' => 'owner',
                            'joined_at' => now(),
                        ],
                    );
                }),
        ];
    }
}
