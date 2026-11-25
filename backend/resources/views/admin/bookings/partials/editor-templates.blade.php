{{-- Row templates of the booking editor (cloned by resources/js/admin/modules/booking-editor.js). --}}
<template data-template="rooms">
    <div class="grid grid-cols-2 items-end gap-2 rounded-control border border-ink-100 p-3 sm:grid-cols-12" data-row>
        <div class="col-span-2 sm:col-span-4">
            <label class="form-label text-xs">Room</label>
            <select class="form-control" data-field-name="room_id" aria-label="Room"><option value="">Choose a room</option></select>
        </div>
        <div class="sm:col-span-2"><label class="form-label text-xs">Adults</label><input type="number" min="0" value="2" class="form-control" data-field-name="adults" aria-label="Adults"></div>
        <div class="sm:col-span-2"><label class="form-label text-xs">Children ages</label><input type="text" placeholder="4, 9" class="form-control" data-field-name="children_ages" aria-label="Children ages"></div>
        <div class="sm:col-span-1"><label class="form-label text-xs">Extra beds</label><input type="number" min="0" value="0" class="form-control" data-field-name="extra_beds" aria-label="Extra beds"></div>
        <div class="sm:col-span-1"><label class="form-label text-xs">Cots</label><input type="number" min="0" value="0" class="form-control" data-field-name="cots" aria-label="Cots"></div>
        <div class="col-span-2 text-right sm:col-span-2"><button type="button" class="btn-link text-error" data-remove-row>Remove</button></div>
    </div>
</template>
<template data-template="room_options">
    <div class="grid grid-cols-2 items-end gap-2 rounded-control border border-ink-100 p-3 sm:grid-cols-12" data-row>
        <div class="col-span-2 sm:col-span-6">
            <label class="form-label text-xs">Room</label>
            <select class="form-control" data-field-name="room_option_id" aria-label="Optional room"><option value="">Choose a room</option></select>
        </div>
        <div class="sm:col-span-2"><label class="form-label text-xs">Adults</label><input type="number" min="0" value="2" class="form-control" data-field-name="adults" aria-label="Adults"></div>
        <div class="sm:col-span-2"><label class="form-label text-xs">Children ages</label><input type="text" placeholder="4, 9" class="form-control" data-field-name="children_ages" aria-label="Children ages"></div>
        <div class="col-span-2 text-right sm:col-span-2"><button type="button" class="btn-link text-error" data-remove-row>Remove</button></div>
    </div>
</template>
