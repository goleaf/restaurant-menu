@props(['idPrefix', 'model', 'languageOptions', 'nameMax' => 160, 'baseNameModel' => null])

<x-menu.translation-fields
    :id-prefix="$idPrefix"
    :model="$model"
    :language-options="$languageOptions"
    :name-max="$nameMax"
    :base-name-model="$baseNameModel"
    :name-only="true"
    {{ $attributes }}
/>
