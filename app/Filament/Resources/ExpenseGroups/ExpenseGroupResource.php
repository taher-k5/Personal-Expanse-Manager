<?php

declare(strict_types=1);

namespace App\Filament\Resources\ExpenseGroups;

use App\Filament\Resources\ExpenseGroups\Pages\EditExpenseGroup;
use App\Filament\Resources\ExpenseGroups\Pages\ListExpenseGroups;
use App\Filament\Resources\ExpenseGroups\Schemas\ExpenseGroupForm;
use App\Filament\Resources\ExpenseGroups\Tables\ExpenseGroupsTable;
use App\Models\ExpenseGroup;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class ExpenseGroupResource extends Resource
{
    protected static ?string $model = ExpenseGroup::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-map';

    protected static string|UnitEnum|null $navigationGroup = 'Shared';

    protected static ?string $modelLabel = 'trip or event';

    protected static ?string $pluralModelLabel = 'trips and events';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return ExpenseGroupForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ExpenseGroupsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereIn('id', auth()->user()->memberships()->pluck('expense_group_id'));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListExpenseGroups::route('/'),
            'edit' => EditExpenseGroup::route('/{record}/edit'),
        ];
    }
}
