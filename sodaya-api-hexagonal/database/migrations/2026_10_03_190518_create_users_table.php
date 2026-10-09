<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Create the user accounts table; staff always belongs to a soda and customers never do. */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('uuidv7()'));
            $table->string('name', 120);
            $table->string('email', 255)->unique();
            $table->string('password', 255);
            $table->text('phone')->nullable();
            $table->string('role', 20);
            $table->foreignUuid('soda_id')->nullable()->constrained()->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestampTz('terms_accepted_at')->nullable();
            $table->timestampTz('whatsapp_consent_at')->nullable();
            $table->timestampsTz();
        });

        DB::statement(<<<'SQL'
            ALTER TABLE users
                ADD CONSTRAINT users_role_check CHECK (role IN ('customer', 'kitchen', 'owner')),
                ADD CONSTRAINT users_soda_id_check CHECK (
                    (role = 'customer' AND soda_id IS NULL) OR (role <> 'customer' AND soda_id IS NOT NULL)
                )
            SQL);
    }

    /** Drop the user accounts table. */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
