<?php

namespace Pannella\Cti;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

abstract class SubtypedModel extends Model
{
    // Name of the subtype table (e.g. assessment_quiz)
    protected $subtypeTable;

    // Attributes that belong to the subtype table
    protected $subtypeAttributes = [];

    // Optionally override if subtype PK column name differs from parent PK
    protected $subtypeKeyName;

    /**
     * Save parent model and subtype data inside a transaction.
     */
    public function save(array $options = [])
    {
        return DB::transaction(function () use ($options) {
            $saved = parent::save($options);

            if ($saved) {
                $this->saveSubtypeData();
            }

            return $saved;
        });
    }

    /**
     * Save subtype table data, insert or update as needed.
     */
    protected function saveSubtypeData()
    {
        if (!$this->subtypeTable) {
            throw new \RuntimeException("Subtype table must be defined.");
        }

        $keyName = $this->subtypeKeyName ?? $this->getKeyName();
        $key = $this->getKey();

        $data = array_intersect_key($this->getAttributes(), array_flip($this->subtypeAttributes));

        if (DB::table($this->subtypeTable)->where($keyName, $key)->exists()) {
            DB::table($this->subtypeTable)->where($keyName, $key)->update($data);
        } else {
            DB::table($this->subtypeTable)->insert(array_merge([$keyName => $key], $data));
        }
    }

    /**
     * Delete subtype row first, then parent row.
     */
    public function delete()
    {
        if ($this->exists && $this->subtypeTable) {
            $keyName = $this->subtypeKeyName ?? $this->getKeyName();
            DB::table($this->subtypeTable)->where($keyName, $this->getKey())->delete();
        }

        return parent::delete();
    }

    /**
     * Load subtype data for this model from the subtype table.
     */
    public function loadSubtypeData()
    {
        if (!$this->subtypeTable) {
            return;
        }

        $keyName = $this->subtypeKeyName ?? $this->getKeyName();
        $key = $this->getKey();

        $data = DB::table($this->subtypeTable)->where($keyName, $key)->first();

        if ($data) {
            $this->forceFill((array) $data);
            $this->exists = true;
        }
    }

    /**
     * Override forceFill to ensure subtype attributes are set.
     */
    public function forceFill(array $attributes)
    {
        foreach ($attributes as $key => $value) {
            if (in_array($key, $this->subtypeAttributes)) {
                $this->setAttribute($key, $value);
            }
        }

        return parent::forceFill($attributes);
    }
}
