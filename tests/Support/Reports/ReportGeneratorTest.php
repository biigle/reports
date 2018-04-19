<?php

namespace Biigle\Tests\Modules\Reports\Support\Reports;

use File;
use Mockery;
use TestCase;
use Exception;
use Biigle\Volume;
use Biigle\Tests\LabelTest;
use Biigle\Tests\VolumeTest;
use Biigle\Tests\ProjectTest;
use Biigle\Modules\Reports\ReportType;
use Biigle\Modules\Reports\Support\Reports\ReportGenerator;
use Biigle\Modules\Reports\Support\Reports\Volumes\Annotations\BasicReportGenerator;

class ReportGeneratorTest extends TestCase
{
    public function testGetNotExists()
    {
        $type = factory(ReportType::class)->make();
        $this->assertNull(ReportGenerator::get(Volume::class, $type));
    }

    public function testGet()
    {
        $type = ReportType::whereName('Annotations\Basic')->first();
        $this->assertInstanceOf(
            BasicReportGenerator::class,
            ReportGenerator::get(Volume::class, $type)
        );
    }

    public function testGetAllExist()
    {
        foreach (ReportType::get() as $type) {
            $this->assertNotNull(ReportGenerator::get(Volume::class, $type));
        }
    }

    public function testHandleException()
    {
        File::shouldReceive('dirname')->andReturn('');
        File::shouldReceive('isDirectory')->andReturn(true);
        File::shouldReceive('exists')->with('somepath')->andReturn(true);
        File::shouldReceive('delete')->with('somepath')->once();

        $this->expectException(Exception::class);
        with(new GeneratorStub(['throw' => true]))->generate(VolumeTest::make(), 'somepath');
    }

    public function testHandleSourceEmpty()
    {
        $this->expectException(Exception::class);
        with(new GeneratorStub)->generate(null, 'somepath');
    }

    public function testHandleRegular()
    {
        File::shouldReceive('dirname')
            ->once()
            ->andReturn('some');

        File::shouldReceive('isDirectory')
            ->once()
            ->with('some')
            ->andReturn(false);

        File::shouldReceive('makeDirectory')
            ->once()
            ->with('some', 0755, true);

        with(new GeneratorStub)->generate(VolumeTest::make(), 'some/path');
    }

    public function testExpandLabelName()
    {
        $root = LabelTest::create();
        $child = LabelTest::create([
            'parent_id' => $root->id,
            'label_tree_id' => $root->label_tree_id,
        ]);

        $generator = new ReportGenerator;
        $this->assertEquals("{$root->name} > {$child->name}", $generator->expandLabelName($child->id));
    }
}

class GeneratorStub extends ReportGenerator
{
    public function generateReport($path)
    {
        $this->tmpFiles[] = Mockery::mock();
        $this->tmpFiles[0]->shouldReceive('delete')->once();

        if ($this->options->get('throw')) {
            throw new Exception;
        }
    }
}
