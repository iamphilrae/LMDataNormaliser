<?php

namespace App\Console\Commands;

use App\Helpers\Utilities;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use League\Csv\Writer;
use Symfony\Component\Console\Output\OutputInterface;

class NormaliseDataBatch extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:normalise-data-batch
            {--no-output : If present, will only create the CSV report, not the output JSON}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reads POI information from a Living Map dataset, normalises it, then returns data as a new dataset. Processes in batch from file: ./storage/app/data/fields_to_batch_normalise.json';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        try {
            $batch_list = Storage::disk('local')->get('data/fields_to_batch_normalise.json');
            $batch_list = json_decode( $batch_list, true );

            if(is_array($batch_list)) {
                foreach ($batch_list as $b) {

                    $artisan_call = [];
                    $artisan_call['schema'] = $b['schema'];
                    $artisan_call['table'] = $b['table'];
                    $artisan_call['field'] = $b['field'];

                    if(!empty($b['primary_key']))
                        $artisan_call['--primary-key'] = $b['primary_key'];

                    if($this->option('no-output'))
                        $artisan_call['--no-output'] = 'true';


                    $this->line('');
                    $this->line('=============');
                    $this->line('');



                    $this->call('app:normalise-data', $artisan_call);
                }
            }
        }
        catch(\Exception $e) {
            Log::critical($e);
            $this->error( $e->getMessage());

            if($this->getOutput()->isVerbose())
                $this->error($e->getTraceAsString());
        }
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
