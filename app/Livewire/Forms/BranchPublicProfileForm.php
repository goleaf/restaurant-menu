<?php

declare(strict_types=1);

namespace App\Livewire\Forms;

use App\Models\Branch;
use App\Support\Branches\BranchPublicContent;
use App\Support\PlainText;
use App\Support\Validation\Branches\BranchProfileRules;
use App\Support\Validation\IndependentSectionValidation;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Livewire\Form;

final class BranchPublicProfileForm extends Form
{
    public mixed $publicName = '';

    public mixed $publicDescription = '';

    public mixed $phone = '';

    public mixed $email = '';

    public mixed $websiteUrl = '';

    public mixed $instagramUrl = '';

    public mixed $facebookUrl = '';

    public mixed $tiktokUrl = '';

    public mixed $translations = [];

    public function populate(Branch $branch): void
    {
        foreach (BranchProfileRules::publicProfileFields() as $property => $column) {
            $this->{$property} = $branch->getAttribute($column) ?? '';
        }
        $translations = BranchPublicContent::translations($branch);
        $this->translations = $translations ?? [];
        if (! is_array($this->translations)) {
            return;
        }
        foreach (['en', 'lt', 'ru'] as $locale) {
            if (! array_key_exists($locale, $this->translations)) {
                $this->translations[$locale] = ['name' => '', 'description' => ''];
            } elseif (is_array($this->translations[$locale])) {
                $this->translations[$locale] = array_replace(['name' => '', 'description' => ''], $this->translations[$locale]);
            }
        }
    }

    /** @return array<string, mixed> */
    public function validatedPayload(): array
    {
        $name = $this->getPropertyName();
        $rules = [];
        foreach ([...BranchProfileRules::branchProfile(), ...BranchProfileRules::publicTranslations()] as $field => $fieldRules) {
            $rules[$name.'.'.$field] = $fieldRules;
        }
        try {
            $validated = Validator::make([$name => $this->all()], $rules, attributes: BranchProfileRules::publicProfileAttributes($name.'.'))->validate()[$name];
        } catch (ValidationException $exception) {
            throw IndependentSectionValidation::preserve($exception, $this->getComponent()->getErrorBag()->getMessages(), $name);
        }
        $result = [];
        foreach (BranchProfileRules::publicProfileFields() as $property => $column) {
            $result[$column] = $validated[$property] ?? null;
        }
        $result['public_translations'] = $validated['translations'] ?? [];

        return $result;
    }

    /** @return array<string, mixed> */
    public function previewAttributes(): array
    {
        $result = [];
        foreach (BranchProfileRules::publicProfileFields() as $property => $column) {
            $limit = match ($column) {
                'public_name' => 160, 'public_description' => 1200, 'phone' => 80, 'email' => 255, default => 2048,
            };
            $result[$column] = PlainText::optional(is_string($this->{$property}) ? $this->{$property} : null, $limit, squish: $column !== 'public_description');
        }
        $translations = is_array($this->translations) ? $this->translations : [];
        $result['public_translations'] = [];
        foreach (['en', 'lt', 'ru'] as $language) {
            $translation = $translations[$language] ?? [];
            $result['public_translations'][$language] = [
                'name' => PlainText::optional(is_array($translation) ? ($translation['name'] ?? null) : null, 160, squish: true),
                'description' => PlainText::optional(is_array($translation) ? ($translation['description'] ?? null) : null, 1200),
            ];
        }

        return $result;
    }
}
