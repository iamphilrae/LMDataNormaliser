<?php

namespace App\Console\Commands;

use App\Helpers\Utilities;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use League\Csv\Writer;
use Symfony\Component\Console\Output\OutputInterface;

class NormaliseData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:normalise-data
            {schema : The database schema to access data from}
            {table : The database table to access data from}
            {field : The database field to retrieve}
            {--no-output : If present, will only create the CSV report, not the output JSON}
            {--primary-key=id : The primary key of the database table}
            {--output=./output/%schema%.%table%.%field%;normalised.json : The format of the output data file (optional)} \
            {--report=./output/%schema%.%table%.%field%;report.csv : The format of the output report file (optional)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reads POI information from a Living Map dataset, normalises it, then returns data as a new dataset.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if (!ini_get('auto_detect_line_endings'))
            ini_set('auto_detect_line_endings', '1');

        $verbose = $this->getOutput()->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE;

        $counters = [
            'records_checked' => 0,
            'records_changed' => 0,
            'records_not_changed' => 0,
            'records_manual_intervention_required' => 0
        ];

        $output = [];
        $report = Utilities::str_putcsv([
            $this->option('primary-key').' (pk)',
            'field',
            'sql',
            'has_changed',
            'manually_intervene',
            'notes']);

        $table = implode('.', [$this->argument('schema'), $this->argument('table')]);

        try {
            $this->info('Checking records for: ' . $table . '.' . $this->argument('field'));
            $data = DB::table($table)
                ->select($this->option('primary-key'), $this->argument('field'))
                ->orderBy($this->option('primary-key'))
                ->get();
        }
        catch(\Exception $e) {
            Log::critical($e);
            $this->error( $e->getMessage());

            if($verbose)
                $this->info( $e->getTraceAsString());

            return;
        }


        foreach ($data as $d)
        {
            $counters['records_checked']++;

            $pkey = $d->{$this->option('primary-key')};
            $content = $d->{$this->argument('field')};
            $old_content = $d->{$this->argument('field')};

            $sql = sprintf('SELECT * FROM %s WHERE %s = %s;', $table, $this->option('primary-key'), Utilities::is_integer($pkey) ? $pkey : "'".$pkey."'");

            $has_changed = false;
            $manually_intervene = false;
            $notes = '';

            /*
             * Perform data normalisation
             */
            try {
                $notes = $this->getNotesAboutContent($content);
                $manually_intervene = !empty($notes) ?? null;

                if(empty($content)) {
                    $notes = "Field is empty, skipping.";
                    $counters['records_not_changed']++;
                }
                else {
                    if(!$manually_intervene) {
                        $normalised_content = $this->getNormalisedContent($content);

                        if($normalised_content !== $content)
                            $has_changed = true;

                        if($has_changed) {
                            $output[] = [
                                $this->option('primary-key') => $pkey,
                                'old_'.$this->argument('field') => $old_content,
                                'new_'.$this->argument('field') => $normalised_content
                            ];


                            $notes = "Field updated.";
                            $counters['records_changed']++;
                        }
                        else {
                            // Don't add to output if no normalisation has taken place
                            $notes = "No changes.";
                            $counters['records_not_changed']++;
                        }
                    }
                    else {
                        $counters['records_manual_intervention_required']++;
                    }
                }
            }
            catch (\Exception $e) {
                $manually_intervene = true;
                $notes = 'Exception: ' . $e->getMessage();

                $counters['records_manual_intervention_required']++;
            }

            /*
             * Generate a report line
             */
            $report .= PHP_EOL.Utilities::str_putcsv([
                    $d->{$this->option('primary-key')},
                    $this->argument('field'),
                    $sql,
                    $has_changed ? 'Y' : '-',
                    $manually_intervene ? 'Y' : '-',
                    $notes
                ]);
        }


        /*
         * Output normalised data file and report.
         */
        try
        {
            if(!$this->option('no-output'))
            {
                $output_filename = str_replace(
                    ['%schema%', '%table%', '%field%'],
                    [$this->argument('schema'), $this->argument('table'), $this->argument('field')],
                    $this->option('output'));

                Storage::disk('local')->put($output_filename, json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            }

            $report_filename = str_replace(
                ['%schema%', '%table%', '%field%'],
                [$this->argument('schema'), $this->argument('table'), $this->argument('field')],
                $this->option('report'));

            Storage::disk('local')->put($report_filename, Writer::createFromString($report));
        }
        catch(\Exception $e) {
            Log::critical($e);
            $this->error( $e->getMessage());

            if($verbose)
                $this->info( $e->getTraceAsString());

            return;
        }

        $this->line('---');
        $this->info('Records found: ' . $counters['records_checked']);

        if($this->option('no-output'))
            $this->info('Records normalised: ' . $counters['records_changed'] . ' (run without --no-output to generate a .json file)');
        else
            $this->info('Records normalised: ' . $counters['records_changed'] . ' (see .json output)');

        $this->info('Records unchanged: ' . $counters['records_not_changed']);
        $this->info('Records require manual intervention: ' . $counters['records_manual_intervention_required'] . ' (see .csv report)');
    }


    /**
     * Checks for whether a given piece of content has any complex changes which will likely need manual intervention. If 'null' is returned then automated normalisation can happen.
     *
     * @param string $str
     * @return string|null
     */
    private function getNotesAboutContent(string|null $str): string|null {

        $note_lines = [];

        /*
         * Check for anything that can cause issues when normalising and so could seek manual intervention.
         */
        if(empty($str))
            return null;


        $str = strtolower($str);

        if(str_contains($str, '<font'))
            $note_lines[] = 'String contains a <font> tag. Check the reason for this formatting.';

        if(str_contains($str, '<style'))
            $note_lines[] = 'String contains a <style> tag. Check the reason for this formatting.';

        if(str_contains($str, '<a'))
            $note_lines[] = 'String contains an <a> tag. Will need to de-link and re-word to suit.';

        if(str_contains($str, '<ul')
            || str_contains($str, '<ol')
            || str_contains($str, '<dl'))
            $note_lines[] = 'String contains a list tag. Will need to re-format manually.';


        if(empty($note_lines))
            return null;

        $response = '';
        foreach($note_lines as $ln)
            $response .= $ln.PHP_EOL;

        return $response;
    }

    /**
     * Reformats and normalises a string so that it can be re-inserted into the DB as a manual process.
     *
     * @param string $str
     * @return string
     */
    private function getNormalisedContent(string $str): string {
        return Utilities::clean_html($str, []);
    }
}
