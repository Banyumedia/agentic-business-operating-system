{{--
    Shell untuk layar detail generik (MP-01). Berbeda dari module-shell.blade.php
    karena selalu memuat DetailScreen (satu pola untuk semua entitas berskema)
    dan meneruskan `id` - module-shell tidak punya konsep parameter record.
--}}
<x-layouts.module :title="$definition['label']" :theme="$theme">
    @livewire('screens.detail-screen', ['module' => $module, 'id' => $id], key('detail-screen-'.$module.'-'.$id))
</x-layouts.module>
