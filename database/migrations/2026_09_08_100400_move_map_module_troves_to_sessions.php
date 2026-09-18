<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Learning-map modules used to attach troves directly; sessions now own that pivot.
     * For each map module with existing trove attachments, find-or-create its first session
     * ('session-1', titled after the module) and move the pivot rows across, preserving
     * order_column. Modules with no attachments are left alone. Re-running is a no-op because
     * the source pivot rows are deleted once moved.
     */
    public function up(): void
    {
        $mapModules = DB::table('curriculum_modules')->where('section', 'map')->get(['id', 'title']);

        foreach ($mapModules as $module) {
            $pivotRows = DB::table('curriculum_module_trove')
                ->where('curriculum_module_id', $module->id)
                ->get();

            if ($pivotRows->isEmpty()) {
                continue;
            }

            $session = DB::table('curriculum_sessions')
                ->where('curriculum_module_id', $module->id)
                ->where('slug', 'session-1')
                ->first();

            $sessionId = $session->id ?? DB::table('curriculum_sessions')->insertGetId([
                'curriculum_module_id' => $module->id,
                'slug' => 'session-1',
                'order_column' => 1,
                'title' => $module->title,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($pivotRows as $row) {
                DB::table('curriculum_session_trove')->updateOrInsert(
                    ['curriculum_session_id' => $sessionId, 'trove_id' => $row->trove_id],
                    ['order_column' => $row->order_column],
                );
            }

            DB::table('curriculum_module_trove')->where('curriculum_module_id', $module->id)->delete();
        }
    }

    /**
     * One-way conversion: the moved pivot rows and any newly created session are not tracked
     * separately from pre-existing ones, so down() is a no-op.
     */
    public function down(): void {}
};
