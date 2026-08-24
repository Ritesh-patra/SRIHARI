<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_assignments', function (Blueprint $table) {
            if (! Schema::hasColumn('work_assignments', 'zone_id')) {
                $table->foreignId('zone_id')->nullable()->after('feeder_id')->constrained('zones')->nullOnDelete();
            }
            if (! Schema::hasColumn('work_assignments', 'started_at')) {
                $table->timestamp('started_at')->nullable()->after('status');
            }
            if (! Schema::hasColumn('work_assignments', 'reassigned_from')) {
                $table->foreignId('reassigned_from')->nullable()->after('assigned_by')->constrained('users')->nullOnDelete();
            }
        });

        // Normalize legacy in_progress → started
        DB::table('work_assignments')
            ->where('status', 'in_progress')
            ->update([
                'status' => 'started',
                'started_at' => DB::raw('COALESCE(started_at, updated_at, created_at)'),
            ]);

        // Backfill zone_id from feeder → substation (portable across sqlite/mysql)
        $rows = DB::table('work_assignments')
            ->whereNull('zone_id')
            ->whereNotNull('feeder_id')
            ->get(['id', 'feeder_id']);

        foreach ($rows as $row) {
            $zoneId = DB::table('feeders')
                ->join('substations', 'substations.id', '=', 'feeders.substation_id')
                ->where('feeders.id', $row->feeder_id)
                ->value('substations.zone_id');

            if ($zoneId) {
                DB::table('work_assignments')->where('id', $row->id)->update(['zone_id' => $zoneId]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('work_assignments', function (Blueprint $table) {
            if (Schema::hasColumn('work_assignments', 'reassigned_from')) {
                $table->dropConstrainedForeignId('reassigned_from');
            }
            if (Schema::hasColumn('work_assignments', 'started_at')) {
                $table->dropColumn('started_at');
            }
            if (Schema::hasColumn('work_assignments', 'zone_id')) {
                $table->dropConstrainedForeignId('zone_id');
            }
        });
    }
};
