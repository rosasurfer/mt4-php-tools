<?php
declare(strict_types=1);

namespace rosasurfer\rt\console;

use JsonException;

use JsonSchema\Validator;

use rosasurfer\ministruts\console\Command;
use rosasurfer\ministruts\console\io\Input;
use rosasurfer\ministruts\console\io\Output;

use function rosasurfer\ministruts\json_decode_or_throw;
use function rosasurfer\ministruts\strEndsWithI;
use function rosasurfer\ministruts\strLeftTo;

use const rosasurfer\ministruts\NL;


/**
 * TopstepConvertHistoryCommand
 *
 * Convert a Topstep account's trade history from "Dashboard" JSON format to TopstepX's CSV format.
 */
class TopstepConvertHistoryCommand extends Command
{
    /** @var string */
    public const DOCOPT = <<<DOCOPT
    Convert a Topstep account history from "Dashboard" JSON format to TopstepX's CSV format.
    
    Usage:
      {:cmd:}  -i INPUT_FILE [-o OUTPUT_FILE] [-v] [-h]
    
    Options:
      -i INPUT_FILE   JSON input file to convert.
      -o OUTPUT_FILE  CSV output file name (default: input file name with .csv extension).
      -v              Enable verbose error output.
      -h, --help      This help screen.
    
    DOCOPT;

    /** @var string - JSON schema for a list of blog categories */
    public const JSON_SCHEMA = '{
        "type": "object",
        "properties": {
            "status": { "type": "boolean" },
            "code":   { "type": "integer" },
            "error":  { "type": "string" },
            "data":   {
                "type": "object",
                "properties": {
                    "categories": {
                        "type":  "array",
                        "items": { "$ref": "#/definitions/Category" }
                    },
                    "count": { "type": "integer" }
                },
                "required": ["categories", "count"]
            }
        },
        "required": ["status", "code", "error", "data"],

        "definitions": {
            "Category": {
                "type": "object",
                "properties": {
                    "id":                   { "type": "integer" },
                    "created":              { "type": "string" },
                    "parent_id":            { "type": "integer" },
                    "status":               { "type": "integer" },
                    "order":                { "type": "integer" },
                    "image":                { "type": ["null", "string"] },
                    "is_visible":           { "type": "integer" },
                    "is_deleted":           { "type": ["null", "integer"] },
                    "category_description": {
                        "type": ["null", "object"],
                        "properties": {
                            "category_id":      { "type": "integer" },
                            "language_id":      { "type": "integer" },
                            "name":             { "type": "string" },
                            "meta_keywords":    { "type": ["null", "string"] },
                            "meta_description": { "type": ["null", "string"] },
                            "seo":              { "type": "string" },
                            "created":          { "type": ["null", "string"] }
                        },
                        "required": ["category_id", "language_id", "name", "meta_keywords", "meta_description", "seo", "created"]
                    }
                },
                "required": ["id", "created", "parent_id", "status", "order", "image", "is_visible", "is_deleted", "category_description"]
            }
        }
    }';


    /** @var string */
    protected $inputFile;

    /** @var object */
    protected $inputJson;

    /** @var string */
    protected $outputFile;


    /**
     * Validate command line arguments logically.
     *
     * @param  Input  $input
     * @param  Output $output
     *
     * @return int - error status (0 for no error)
     */
    protected function validate(Input $input, Output $output): int {
        $error = function($status, $message) use ($input, $output) {
            $usage = $input->getDocoptResult()->getUsage();
            $output->error($message.NL.NL.$usage);
            return $status;
        };

        // -i
        if (!is_file($inputFile = $input->getOption('-i'))) return $error(1, 'error: input file "'.$inputFile.'" not found');
        $this->inputFile = $inputFile;

        // validate JSON input
        $content = file_get_contents($inputFile);
        if (!is_string($content)) return $error(1, 'error: can\'t read input file "'.$inputFile.'"');
        try {
            $jsonData = json_decode_or_throw($content);
        }
        catch (JsonException $ex) {
            return $error(1, 'error: format error in "'.$inputFile.'" (invalid JSON)');
        }
        try {
            $jsonSchema = json_decode_or_throw(self::JSON_SCHEMA);
        }
        catch (JsonException $ex) {
            return $error(1, 'error: format error in '.__CLASS__.'::JSON_SCHEMA (invalid JSON)');
        }
        $validator = new Validator();
        $validator->validate($jsonData, $jsonSchema);

        if (!$validator->isValid()) {
            $errorMsg = 'error: unexpected JSON format in input file "'.$inputFile.'"';
            if ($input->getOption('-v')) {
                $errorMsg .= NL.NL.print_r($validator->getErrors(), true);
            }
            return $error(1, $errorMsg);
        }
        $this->inputJson = $jsonData;

        // -o
        $outputFile = $input->getOption('-o');
        if (is_string($outputFile)) {
            if (!strEndsWithI($outputFile, '.csv')) {
                $outputFile .= '.csv';
            }
        }
        elseif (strEndsWithI($inputFile, '.json')) {
            $outputFile = strLeftTo($inputFile, '.', -1).'.csv';
        }
        else {
            $outputFile = $inputFile.'.csv';
        }
        if (is_file($outputFile)) return $error(1, 'error: output file "'.$outputFile.'" already exists');
        $this->outputFile = $outputFile;

        return 0;
    }


    /**
     * @param  Input  $input
     * @param  Output $output
     *
     * @return int - execution status (0 for success)
     */
    protected function execute(Input $input, Output $output): int {

        echof('input:  '.$this->inputFile);
        echof('output: '.$this->outputFile);

        return 0;
    }
}
