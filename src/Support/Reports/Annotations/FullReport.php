<?php

namespace Dias\Modules\Export\Support\Reports\Annotations;

use DB;
use Dias\Project;
use Dias\Modules\Export\Support\CsvFile;
use Dias\Modules\Export\Support\Reports\ExecutesPythonScript;

class FullReport extends AnnotationReport
{
    use ExecutesPythonScript;

    /**
     * Create an image label report instance.
     *
     * @param Project $project The project for which the report should be generated.
     * @param bool $restricted Is the report restricted to the export area?
     */
    public function __construct(Project $project, $restricted)
    {
        parent::__construct($project, $restricted);
        $this->name = 'full annotation report';
        $this->filename = 'full_annotation_report';
        $this->extension = 'xlsx';
    }

    /**
     * Generate the report.
     *
     * @return void
     */
    public function generateReport()
    {
        $transects = $this->project->transects()
            ->pluck('name', 'id');

        foreach ($transects as $id => $name) {
            $csv = CsvFile::makeTmp();
            $this->tmpFiles[] = $csv;

            // put transect name to first line
            $csv->put([$name]);

            $query = $this->query($id);
            $rows = $query->get();

            // CHUNKING IS BROKEN SOMEHOW!
            // $query->chunkById(500, function ($rows) use ($csv) {
                foreach ($rows as $row) {
                    $csv->put([
                        $row->filename,
                        $row->annotation_id,
                        $row->label_name,
                        $row->shape_name,
                        $row->points,
                        $row->attrs
                    ]);
                }
            // }, 'annotation_labels.id', 'annotation_labels_id');

            $csv->close();
        }

        $this->executeScript('full_report');
    }

    /**
     * Assemble a new DB query for a transect.
     *
     * @param int $id Transect ID
     *
     * @return \Illuminate\Database\Query\Builder
     */
    protected function query($id)
    {
        return DB::table('annotation_labels')
            ->join('labels', 'annotation_labels.label_id', '=', 'labels.id')
            ->join('annotations', 'annotation_labels.annotation_id', '=', 'annotations.id')
            ->join('images', 'annotations.image_id', '=', 'images.id')
            ->join('shapes', 'annotations.shape_id', '=', 'shapes.id')
            ->select(
                'annotation_labels.id as annotation_labels_id', // required for chunkById
                'images.filename',
                'annotations.id as annotation_id',
                'labels.name as label_name',
                'shapes.name as shape_name',
                'annotations.points',
                'images.attrs'
            )
            ->where('images.transect_id', $id)
            ->when($this->restricted, function ($query) use ($id) {
                return $query->whereNotIn('annotations.id', $this->getSkipIds($id));
            })
            ->orderBy('annotations.id');
    }
}