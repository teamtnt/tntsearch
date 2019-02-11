<?php

use TeamTNT\TNTSearch\Classifier\TNTClassifier;

class TNTClassifierTest extends PHPUnit\Framework\TestCase
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
        $precision = number_format(($guessCount * 100 / $counter), 4);
        $this->assertGreaterThanOrEqual(98, $precision);
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

    public function testPredictClass2()
    {
        $classifier = new TNTClassifier();
        $classifier->learn("A great game", "Sports");
        $classifier->learn("The election was over", "Not sports");
        $classifier->learn("Very clean match", "Sports");
        $classifier->learn("A clean but forgettable game", "Sports");

        $guess = $classifier->predict("It was a close election");
        $this->assertEquals("Not sports", $guess['label']);
    }
}
