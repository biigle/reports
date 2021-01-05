<?php

namespace Biigle\Modules\Reports\Support\Reports\Projects\ImageLabels;

use Biigle\Modules\Reports\Support\Reports\Projects\ProjectImageReportGenerator;
use Biigle\Modules\Reports\Support\Reports\Volumes\ImageLabels\ImageLocationReportGenerator as ReportGenerator;

class ImageLocationReportGenerator extends ProjectImageReportGenerator
{
    /**
     * The class of the volume report to use for this project report.
     *
     * @var string
     */
    protected $reportClass = ReportGenerator::class;

    /**
     * Name of the report for use in text.
     *
     * @var string
     */
    protected $name = 'image location image label report';

    /**
     * Name of the report for use as (part of) a filename.
     *
     * @var string
     */
    protected $filename = 'image_location_image_label_report';
}