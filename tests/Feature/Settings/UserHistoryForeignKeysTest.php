<?php

declare(strict_types=1);

use App\Domain\Settings\Guards\UserHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

// S4: la lista de claves foráneas a users vive en UserHistory::FOREIGN_KEYS. Si una
// migración nueva agrega otra, este test falla y obliga a decidir si cuenta como historial.

it('lists every foreign key to users except role_user [S4]', function () {
    $rows = DB::select(<<<'SQL'
        select kcu.table_name as table_name, kcu.column_name as column_name
        from information_schema.table_constraints tc
        join information_schema.key_column_usage kcu
          on kcu.constraint_name = tc.constraint_name and kcu.constraint_schema = tc.constraint_schema
        join information_schema.constraint_column_usage ccu
          on ccu.constraint_name = tc.constraint_name and ccu.constraint_schema = tc.constraint_schema
        where tc.constraint_type = 'FOREIGN KEY'
          and ccu.table_name = 'users'
          and tc.table_schema = current_schema()
        SQL);

    $found = [];
    foreach ($rows as $row) {
        if ($row->table_name === 'role_user') {
            continue;
        }
        $found[] = $row->table_name.'.'.$row->column_name;
    }

    $declared = [];
    foreach (UserHistory::FOREIGN_KEYS as $table => $columns) {
        foreach ($columns as $column) {
            $declared[] = $table.'.'.$column;
        }
    }

    sort($found);
    sort($declared);

    expect($declared)->toBe($found);
});
