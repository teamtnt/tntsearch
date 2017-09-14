<?php

use TeamTNT\TNTSearch\Classifier\TNTClassifier;

class TNTClassifierTest extends PHPUnit_Framework_TestCase
{
    public function testOpenMNISTLables()
    {
        $testLabelsLocation = __DIR__ . '/../_files/mnist/t10k-labels-idx1-ubyte';
        $trainLabelsLocation = __DIR__ . '/../_files/mnist/train-labels-idx1-ubyte';
        
        $testImagesLocation = __DIR__ . '/../_files/mnist/t10k-images-idx3-ubyte';
        $trainImagesLocation = __DIR__ . '/../_files/mnist/train-images-idx3-ubyte';
        
        $labels = $this->loadMNISTLabels($testLabelsLocation);
        $images = $this->loadMNISTImages($testImagesLocation);

        for ($i=0; $i < 100; $i++) { 
            $this->createImage($images[$i], $i . "num-" .$labels[$i]);
        }
    }

    public function loadMNISTLabels($filename)
    {
        $fp = fopen($filename, 'rb');

        $array = unpack("N2", fread($fp, 8));
        $magic = $array[1];

        if($magic != 2049) {
            throw new Exception("Bad magic number in $filename", 1);
        }

        $numLabels = $array[2];

        $stream = stream_get_contents($fp);

        $labels = unpack("C$numLabels", $stream);

        fclose($fp);

        return array_values($labels);
    }

    public function loadMNISTImages($filename)
    {
        $fp = fopen($filename, 'rb');

        $array = unpack("N4", fread($fp, 16));
        $magic = $array[1];

        if($magic != 2051) {
            throw new Exception("Bad magic number in $filename", 1);
        }

        $numOfImages  = $array[2];
        $numOfRows    = $array[3];
        $numOfColumns = $array[4];

        $pixelsPerImage = $numOfRows * $numOfColumns;

        $images = [];

        while ( $stream = fread($fp, $pixelsPerImage) ) {
            $image = unpack("C{$pixelsPerImage}", $stream);
            $images[] = implode(',', $image);
        }

        fclose($fp);

        return $images;
    }

    public function createImage($image, $name)
    {
        $gd = imagecreatetruecolor(28, 28);

        $pixels = explode(',', $image);

        for ($i = 0; $i < 28; $i++) {
            for ($j=0; $j < 28; $j++) { 
                $value = 255 - $pixels [ $i*28 + $j ];
                $color = imagecolorallocate($gd, $value, $value, $value);
                imagesetpixel($gd, $j, $i, $color);
            }
        }
         
        imagepng($gd, __DIR__ . "/../_files/mnist/images/$name.png");
    }

    public function testPredictFruit()
    {
        return;
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

            if ($guess == $sms[$i]->label) {
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
        $this->assertEquals("c", $guess);
    }
}
