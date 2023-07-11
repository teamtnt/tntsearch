<?php

use TeamTNT\TNTSearch\Spell\JaroWinklerDistance;

class JaroWinklerDistanceTest extends PHPUnit\Framework\TestCase
{
    protected $sd;

    public function setUp(): void
    {
        $this->sd = new JaroWinklerDistance();
    }

    public function testJaro()
    {
        $d = $this->sd->jaro('DWAYNE', 'DUANE');
        $this->assertEqualsWithDelta(0.822, $d, 0.001);

        $d = $this->sd->jaro("MARTHA", "MARHTA");
        $this->assertEqualsWithDelta(0.944444, $d, 0.001);

        $d = $this->sd->jaro("DIXON", "DICKSONX");
        $this->assertEqualsWithDelta(0.766667, $d, 0.001);

        $d = $this->sd->jaro("JELLYFISH", "SMELLYFISH");
        $this->assertEqualsWithDelta(0.896296, $d, 0.001);
    }

    public function testGetDistance()
    {
        $d = $this->sd->getDistance("al", "al");
        $this->assertEquals(1.0, $d);
        $d = $this->sd->getDistance("martha", "marhta");
        $this->assertGreaterThan(0.961, $d);
        $this->assertLessThan(0.962, $d);
        $d = $this->sd->getDistance("jones", "johnson");
        $this->assertGreaterThanOrEqual(0.832, $d);
        $this->assertLessThanOrEqual(0.833, $d);
        $d = $this->sd->getDistance("dwayne", "duane");
        $this->assertGreaterThanOrEqual(0.84, $d);
        $this->assertLessThanOrEqual(0.841, $d);
        $d = $this->sd->getDistance("dixon", "dicksonx");
        $this->assertGreaterThanOrEqual(0.813, $d);
        $this->assertLessThanOrEqual(0.814, $d);
        $d = $this->sd->getDistance("fvie", "ten");
        $this->assertEquals(0, $d);
        $d1 = $this->sd->getDistance("zac ephron", "zac efron");
        $d2 = $this->sd->getDistance("zac ephron", "kai ephron");
        $this->assertTrue($d1 > $d2);
        $d1 = $this->sd->getDistance("brittney spears", "britney spears");
        $d2 = $this->sd->getDistance("brittney spears", "brittney startzman");
        $this->assertTrue($d1 > $d2);
    }
}
