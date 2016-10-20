<?php

namespace Dias\Modules\Export\Support\Reports\Transects\Annotations;

use DB;
use Dias\LabelTree;
use Dias\Modules\Export\Support\CsvFile;

class BasicReport extends Report
{
    /**
     * Name of the report for use in text.
     *
     * @var string
     */
    protected $name = 'basic annotation report';

    /**
     * Name of the report for use as (part of) a filename.
     *
     * @var string
     */
    protected $filename = 'basic_annotation_report';

    /**
     * File extension of the report file.
     *
     * @var string
     */
    protected $extension = 'pdf';

    /**
     * Generate the report.
     *
     * @return void
     */
    public function generateReport()
    {
        $labels = $this->query()->get();

        if ($this->shouldSeparateLabelTrees()) {
            $labels = $labels->groupBy('label_tree_id');
            $trees = LabelTree::whereIn('id', $labels->keys())->pluck('name', 'id');

            foreach ($trees as $id => $name) {
                $this->tmpFiles[] = $this->createCsv($labels->get($id), $name);
            }

        } else {
            $this->tmpFiles[] = $this->createCsv($labels);
        }

        $this->executeScript('basic_report');
    }

    /**
     * Assemble a new DB query for the transect of this report.
     *
     * @return \Illuminate\Database\Query\Builder
     */
    protected function query()
    {
        return DB::table('labels')
            ->join('annotation_labels', 'annotation_labels.label_id', '=', 'labels.id')
            ->join('annotations', 'annotation_labels.annotation_id', '=', 'annotations.id')
            ->join('images', 'annotations.image_id', '=', 'images.id')
            ->where('images.transect_id', $this->transect->id)
            ->when($this->isRestrictedToExportArea(), [$this, 'restrictToExportAreaQuery'])
            ->when($this->isRestrictedToAnnotationSession(), [$this, 'restrictToAnnotationSessionQuery'])
            ->select(DB::raw('labels.name, labels.color, count(labels.id) as count, labels.label_tree_id'))
            ->groupBy('labels.id')
            ->orderBy('labels.id');
    }

    /**
     * Create a CSV file for a single plot of this report
     *
     * @param \Illuminate\Support\Collection $labels The labels/rows for the CSV
     * @param string $title The title to put in the first row of the CSV
     * @return CsvFile
     */
    protected function createCsv($labels, $title = '')
    {
        $csv = CsvFile::makeTmp();
        $csv->put([$title]);

        foreach ($labels as $label) {
            $csv->put([$label->name, $label->color, $label->count]);
        }

        $csv->close();

        return $csv;
    }
}