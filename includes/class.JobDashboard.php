<?php
/**
 * Tabla con los trabajos pendientes y fallidos por cola de la colección jobs.
 *
 *   echo (new JobDashboard())->render();
 */
class JobDashboard
{
    /**
     * @global MongoDB\Client $m
     * @global class_config $config
     * @return string HTML (<table>).
     */
    public function render()
    {
        global $m, $config;
        $stats = $m->{$config['sitedb']}->jobs->aggregate([
            ['$group' => [
                '_id' => '$queue',
                'pending' => ['$sum' => ['$cond' => [['$eq' => ['$failed_at', null]], 1, 0]]],
                'failed' => ['$sum' => ['$cond' => [['$ne' => ['$failed_at', null]], 1, 0]]],
            ]]
        ]);

        $html = '<table class="table striped">';
        foreach ($stats as $s) {
            $html .= "<tr><td>{$s['_id']}</td><td>{$s['pending']}</td><td>{$s['failed']}</td></tr>";
        }
        $html .= '</table>';
        return $html;
    }
}
