<?php

use TeamTNT\TNTSearch\Classifier\TNTClassifier;

class TNTClassifierTest extends PHPUnit_Framework_TestCase
{
    public function testPredictSpamHam()
    {
        $smsJSON = file_get_contents(__DIR__.'/../_files/sms-texts.json');
        $sms     = json_decode($smsJSON);

        $training = 0.80;

        $classifier = new TNTClassifier();

        for ($i = 0; $i <= count($sms) * $training; $i++) {
            $classifier->learn($sms[$i]->message, $sms[$i]->label);
        }

        $guessCount = 0;
        $counter    = 0;

        for ($i = round(count($sms) * $training); $i < count($sms); $i++) {
            $counter++;
            $guess = $classifier->predict($sms[$i]->message);

            if ($guess['label'] == $sms[$i]->label) {
                $guessCount++;
            }

        }

        echo "\nSuccess rate is: ".number_format(($guessCount * 100 / $counter), 4)."%";
    }

    public function testPredictClass()
    {
        $classifier = new TNTClassifier();
        $classifier->learn("chinese beijing chinese", "c");
        $classifier->learn("chinese chinese shangai", "c");
        $classifier->learn("chinese macao", "c");
        $classifier->learn("tokyo japan chinese", "j");

        $guess = $classifier->predict("chinese chinese chinese tokyo japan");
        $this->assertEquals("c", $guess['label']);
    }
}
