<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Tables that hold data belonging to a single barbershop.
     *
     * @var array<int, string>
     */
    protected array $tenantTables = [
        'users',
        'settings',
        'exchange_rates',
        'services',
        'products',
        'sales',
        'sale_items',
        'stock_movements',
        'closings',
        'expenses',
        'password_reset_requests',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('companies')) {
            Schema::create('companies', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->string('logo_path')->nullable();
                $table->string('email')->nullable();
                $table->string('phone', 30)->nullable();
                $table->string('address')->nullable();
                $table->boolean('active')->default(true);
                $table->timestamps();
            });
        }

        foreach ($this->tenantTables as $table) {
            $this->addCompanyId($table);
        }

        if (! Schema::hasColumn('users', 'is_super_admin')) {
            Schema::table('users', function (Blueprint $table) {
                $table->boolean('is_super_admin')->default(false)->after('company_id');
            });
        }

        $defaultCompanyId = $this->ensureExistingDataHasCompany();

        $this->replaceUniqueIndexes();

        if ($defaultCompanyId !== null) {
            foreach ($this->tenantTables as $table) {
                if ($table === 'users') {
                    continue;
                }

                DB::table($table)
                    ->whereNull('company_id')
                    ->update(['company_id' => $defaultCompanyId]);
            }

            DB::table('users')
                ->whereNull('company_id')
                ->where('is_super_admin', false)
                ->update(['company_id' => $defaultCompanyId]);
        }
    }

    public function down(): void
    {
        $this->restoreUniqueIndexes();

        if (Schema::hasTable('users') && Schema::hasColumn('users', 'is_super_admin')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('is_super_admin');
            });
        }

        foreach (array_reverse($this->tenantTables) as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'company_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $table) {
                try {
                    $table->dropForeign(['company_id']);
                } catch (Throwable $e) {
                    // SQLite does not create alter-table foreign keys.
                }

                try {
                    $table->dropIndex(['company_id']);
                } catch (Throwable $e) {
                    // Index may not exist on every driver.
                }

                $table->dropColumn('company_id');
            });
        }

        Schema::dropIfExists('companies');
    }

    protected function addCompanyId(string $table): void
    {
        if (! Schema::hasTable($table) || Schema::hasColumn($table, 'company_id')) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) {
            $blueprint->foreignId('company_id')
                ->nullable()
                ->index()
                ->constrained('companies')
                ->nullOnDelete();
        });
    }

    /**
     * Create a company for data that already exists before this migration and
     * return its id. Fresh installations do not get a default company.
     */
    protected function ensureExistingDataHasCompany(): ?int
    {
        $tablesWithData = ['users', 'settings', 'services', 'products', 'sales', 'expenses', 'closings'];

        $hasExistingData = collect($tablesWithData)->contains(function (string $table) {
            return Schema::hasTable($table) && DB::table($table)->exists();
        });

        if (! $hasExistingData) {
            return null;
        }

        if (DB::table('companies')->exists()) {
            return (int) DB::table('companies')->orderBy('id')->value('id');
        }

        $name = Schema::hasTable('settings')
            ? (DB::table('settings')->where('key', 'shop_name')->value('value') ?: config('app.name'))
            : config('app.name');

        $name = $name ?: 'Mi Barbería';
        $slug = Str::slug($name) ?: 'principal';
        $base = $slug;
        $suffix = 2;

        while (DB::table('companies')->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return (int) DB::table('companies')->insertGetId([
            'name' => $name,
            'slug' => $slug,
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function replaceUniqueIndexes(): void
    {
        $drops = [
            'settings' => ['settings_key_unique'],
            'products' => ['products_sku_unique'],
            'sales' => ['sales_code_unique'],
        ];

        foreach ($drops as $table => $indexes) {
            foreach ($indexes as $index) {
                try {
                    Schema::table($table, function (Blueprint $blueprint) use ($index) {
                        $blueprint->dropUnique($index);
                    });
                } catch (Throwable $e) {
                    // The index is already gone or was never created.
                }
            }
        }

        $this->addUnique('settings', ['company_id', 'key'], 'settings_company_key_unique');
        $this->addUnique('products', ['company_id', 'sku'], 'products_company_sku_unique');
        $this->addUnique('sales', ['company_id', 'code'], 'sales_company_code_unique');
        $this->addIndex('closings', ['company_id', 'barber_id', 'period_type', 'period_start', 'period_end'], 'closings_company_period_index');
        $this->addIndex('sales', ['company_id', 'sold_at'], 'sales_company_sold_at_index');
    }

    protected function restoreUniqueIndexes(): void
    {
        foreach ([
            'settings' => 'settings_company_key_unique',
            'products' => 'products_company_sku_unique',
            'sales' => 'sales_company_code_unique',
        ] as $table => $index) {
            try {
                Schema::table($table, function (Blueprint $blueprint) use ($index) {
                    $blueprint->dropUnique($index);
                });
            } catch (Throwable $e) {
                // Index may not exist.
            }
        }

        foreach ([
            'settings' => ['key', 'settings_key_unique'],
            'products' => ['sku', 'products_sku_unique'],
            'sales' => ['code', 'sales_code_unique'],
        ] as $table => [$column, $index]) {
            try {
                Schema::table($table, function (Blueprint $blueprint) use ($column, $index) {
                    $blueprint->unique($column, $index);
                });
            } catch (Throwable $e) {
                // Index may already exist.
            }
        }
    }

    protected function addUnique(string $table, array $columns, string $name): void
    {
        try {
            Schema::table($table, function (Blueprint $blueprint) use ($columns, $name) {
                $blueprint->unique($columns, $name);
            });
        } catch (Throwable $e) {
            // Index may already exist.
        }
    }

    protected function addIndex(string $table, array $columns, string $name): void
    {
        try {
            Schema::table($table, function (Blueprint $blueprint) use ($columns, $name) {
                $blueprint->index($columns, $name);
            });
        } catch (Throwable $e) {
            // Index may already exist.
        }
    }
};
