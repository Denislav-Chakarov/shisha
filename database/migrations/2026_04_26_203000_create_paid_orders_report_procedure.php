<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::unprepared('DROP PROCEDURE IF EXISTS sp_paid_orders_report');
        DB::unprepared(<<<'SQL'
CREATE PROCEDURE sp_paid_orders_report(
    IN p_start DATETIME,
    IN p_end DATETIME,
    IN p_table_id BIGINT
)
BEGIN
    SELECT
        o.id AS order_id,
        st.table_number,
        o.total_amount,
        o.closed_at,
        COUNT(oi.id) AS items_count
    FROM orders o
    INNER JOIN store_tables st ON st.id = o.store_table_id
    LEFT JOIN order_items oi ON oi.order_id = o.id
    WHERE o.status = 'paid'
      AND o.closed_at BETWEEN p_start AND p_end
      AND (p_table_id IS NULL OR o.store_table_id = p_table_id)
    GROUP BY o.id, st.table_number, o.total_amount, o.closed_at
    ORDER BY o.closed_at DESC;
END
SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::unprepared('DROP PROCEDURE IF EXISTS sp_paid_orders_report');
    }
};
