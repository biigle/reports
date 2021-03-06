<?php

namespace Biigle\Modules\Reports\Support\Reports\Volumes\ImageAnnotations;

use Biigle\Modules\Reports\Support\Reports\Volumes\VolumeReportGenerator;
use Biigle\Modules\Reports\Volume;
use DB;
use Illuminate\Support\Str;

class AnnotationReportGenerator extends VolumeReportGenerator
{
    /**
     * Get the report name.
     *
     * @return string
     */
    public function getName()
    {
        $restrictions = [];

        if ($this->isRestrictedToExportArea()) {
            $restrictions[] = 'export area';
        }

        if ($this->isRestrictedToAnnotationSession()) {
            $name = $this->getAnnotationSessionName();
            $restrictions[] = "annotation session {$name}";
        }

        if ($this->isRestrictedToNewestLabel()) {
            $restrictions[] = 'newest label of each annotation';
        }

        if (!empty($restrictions)) {
            $suffix = implode(', ', $restrictions);

            return "{$this->name} (restricted to {$suffix})";
        }

        return $this->name;
    }

    /**
     * Get the filename.
     *
     * @return string
     */
    public function getFilename()
    {
        $restrictions = [];

        if ($this->isRestrictedToExportArea()) {
            $restrictions[] = 'export_area';
        }

        if ($this->isRestrictedToAnnotationSession()) {
            $name = Str::slug($this->getAnnotationSessionName());
            $restrictions[] = "annotation_session_{$name}";
        }

        if ($this->isRestrictedToNewestLabel()) {
            $restrictions[] = 'newest_label';
        }

        if (!empty($restrictions)) {
            $suffix = implode('_', $restrictions);

            return "{$this->filename}_restricted_to_{$suffix}";
        }

        return $this->filename;
    }

    /**
     * Callback to be used in a `when` query statement that restricts the resulting annotations to the export area of the reansect of this report (if there is any).
     *
     * @param \Illuminate\Database\Query\Builder $query
     * @return \Illuminate\Database\Query\Builder
     */
    public function restrictToExportAreaQuery($query)
    {
        return $query->whereNotIn('image_annotations.id', $this->getSkipIds());
    }

    /**
     * Callback to be used in a `when` query statement that restricts the resulting annotation labels to the annotation session of this report.
     *
     * @param \Illuminate\Database\Query\Builder $query
     * @return \Illuminate\Database\Query\Builder
     */
    public function restrictToAnnotationSessionQuery($query)
    {
        $session = $this->getAnnotationSession();

        return $query->where(function ($query) use ($session) {
            // take only annotations that belong to the time span...
            $query->where('image_annotations.created_at', '>=', $session->starts_at)
                ->where('image_annotations.created_at', '<', $session->ends_at)
                // ...and to the users of the session
                ->whereIn('image_annotation_labels.user_id', function ($query) use ($session) {
                    $query->select('user_id')
                        ->from('annotation_session_user')
                        ->where('annotation_session_id', $session->id);
                });
        });
    }

    /**
     * Callback to be used in a `when` query statement that restricts the results to the newest annotation labels of each annotation.
     *
     * @param \Illuminate\Database\Query\Builder $query
     * @return \Illuminate\Database\Query\Builder
     */
    public function restrictToNewestLabelQuery($query)
    {
        // This is a quite inefficient query. Here is why:
        // We could use "select distinct on" directly on the query but this would be
        // overridden by the subsequent select() in self::initQuery(). If we would add
        // the "select distinct on" **after** the select(), we would get invalid syntax:
        // "select *, distinct on".
        return $query->whereIn('image_annotation_labels.id', function ($query) {
            return $query->selectRaw('distinct on (annotation_id) id')
                ->from('image_annotation_labels')
                ->orderBy('annotation_id', 'desc')
                ->orderBy('id', 'desc')
                ->orderBy('created_at', 'desc');
        });
    }

    /**
     * Returns the annotation IDs to skip as outside of the volume export area.
     *
     * We collect the IDs to skip rather than the IDs to include since there are probably
     * fewer annotations outside of the export area.
     *
     * @return array Annotation IDs
     */
    protected function getSkipIds()
    {
        $skip = [];
        $exportArea = Volume::convert($this->source)->exportArea;

        if (!$exportArea) {
            // take all annotations if no export area is specified
            return $skip;
        }

        $exportArea = [
            // min x
            min($exportArea[0], $exportArea[2]),
            // min y
            min($exportArea[1], $exportArea[3]),
            // max x
            max($exportArea[0], $exportArea[2]),
            // max y
            max($exportArea[1], $exportArea[3]),
        ];

        $handleChunk = function ($annotations) use ($exportArea, &$skip) {
            foreach ($annotations as $annotation) {
                $points = json_decode($annotation->points);
                $size = sizeof($points);
                // Works for circles with 3 elements in $points, too!
                for ($x = 0, $y = 1; $y < $size; $x += 2, $y += 2) {
                    if ($points[$x] >= $exportArea[0] &&
                        $points[$x] <= $exportArea[2] &&
                        $points[$y] >= $exportArea[1] &&
                        $points[$y] <= $exportArea[3]) {
                        // As long as one point of the annotation is inside the
                        // area, don't skip it.
                        continue 2;
                    }
                }

                $skip[] = $annotation->id;
            }
        };

        DB::table('image_annotations')
            ->join('images', 'image_annotations.image_id', '=', 'images.id')
            ->where('images.volume_id', $this->source->id)
            ->select('image_annotations.id as id', 'image_annotations.points')
            ->chunkById(500, $handleChunk, 'image_annotations.id', 'id');

        return $skip;
    }

    /**
     * Callback to be used in a `when` query statement that restricts the results to a specific subset of annotation labels.
     *
     * @param \Illuminate\Database\Query\Builder $query
     * @return \Illuminate\Database\Query\Builder
     */
    public function restrictToLabelsQuery($query)
    {
        return $query->whereIn('image_annotation_labels.label_id', $this->getOnlyLabels());
    }

    /**
     * Assembles the part of the DB query that is the same for all annotation reports.
     *
     * @param mixed $columns The columns to select
     * @return \Illuminate\Database\Query\Builder
     */
    public function initQuery($columns = [])
    {
        $query = DB::table('image_annotation_labels')
            ->join('image_annotations', 'image_annotation_labels.annotation_id', '=', 'image_annotations.id')
            ->join('images', 'image_annotations.image_id', '=', 'images.id')
            ->join('labels', 'image_annotation_labels.label_id', '=', 'labels.id')
            ->where('images.volume_id', $this->source->id)
            ->when($this->isRestrictedToExportArea(), [$this, 'restrictToExportAreaQuery'])
            ->when($this->isRestrictedToAnnotationSession(), [$this, 'restrictToAnnotationSessionQuery'])
            ->when($this->isRestrictedToNewestLabel(), [$this, 'restrictToNewestLabelQuery'])
            ->when($this->isRestrictedToLabels(), [$this, 'restrictToLabelsQuery'])
            ->select($columns);

        if ($this->shouldSeparateLabelTrees()) {
            $query->addSelect('labels.label_tree_id');
        } elseif ($this->shouldSeparateUsers()) {
            $query->addSelect('image_annotation_labels.user_id');
        }

        return $query;
    }

    /**
     * Should this report be restricted to the export area?
     *
     * @return bool
     */
    protected function isRestrictedToExportArea()
    {
        return $this->options->get('exportArea', false);
    }

    /**
     * Determines if this report should aggregate child labels.
     *
     * @return bool
     */
    protected function shouldAggregateChildLabels()
    {
        return $this->options->get('aggregateChildLabels', false);
    }
}
