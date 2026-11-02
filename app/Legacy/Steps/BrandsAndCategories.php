<?php

declare(strict_types=1);

namespace App\Legacy\Steps;

use App\Legacy\ImportContext;
use App\Legacy\Transform\Text;
use App\Models\Brand;
use App\Models\Category;
use Illuminate\Database\Eloquent\Model;

/** Step 4: brands and categories, deduplicated by trimmed case-insensitive name within the tenant. Category brand_id is dropped. */
final readonly class BrandsAndCategories implements Step
{
    public function name(): string
    {
        return 'BrandsAndCategories';
    }

    public function run(ImportContext $context): void
    {
        $this->import($context, 'store_brand_list', 'brand_name', Brand::class, 150);
        $this->import($context, 'store_category_list', 'category_name', Category::class, 150);
    }

    /**
     * @param  literal-string  $column
     * @param  class-string<Brand|Category>  $model
     */
    private function import(ImportContext $context, string $table, string $column, string $model, int $length): void
    {
        /** @var array<string, int> $byName */
        $byName = [];
        foreach ($model::query()->withTrashed()->get(['id', 'name']) as $existing) {
            $byName[Text::key($existing->name)] ??= $existing->id;
        }

        $context->select($table, ['id', 'store_id'], [$column])
            ->whereIn('store_id', $context->stores())
            ->orderBy('id')
            ->chunk($context->options->chunk, function ($rows) use ($context, $table, $column, $model, $length, &$byName): void {
                foreach ($rows as $row) {
                    $id = (int) $row->id;
                    if ($context->ids->has($table, $id)) {
                        $context->skipped();

                        continue;
                    }

                    $name = Text::limit($context->text($row->{$column}, $table, $id, $column), $length) ?? "Unnamed #{$id}";
                    $key = Text::key($name);

                    if (isset($byName[$key])) {
                        $context->report->add('merged_duplicate_name', $table, $id, "[{$name}] merged into the existing row of the same name");
                        $context->skipped();
                    } else {
                        /** @var Model $record */
                        $record = new $model;
                        $record->forceFill(['name' => $name, 'is_active' => true, 'legacy_id' => $id])->save();
                        $byName[$key] = (int) $record->getKey();
                        $context->created();
                    }

                    $context->ids->put($table, (int) $row->store_id, $id, $byName[$key]);
                }
            });
    }
}
