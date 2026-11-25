<?php

/*
| Room allocation and stay validation messages (SRS 7.2, 7.3). ":room" is "This room"/"Room" for single-room
| requests and "Room 1", "Room 2"... (position in the request) for multi-room requests.
*/

return [
    'room_number' => 'Room :number',
    'this_room' => 'This room',
    'room' => 'Room',
    'every_guest' => 'Every guest must be in a room.',
    'cannot_fit' => 'These rooms cannot fit :count guests, add a room.',
    'capacity' => ':room exceeds its capacity of :max.',
    'max_adults' => ':room sleeps at most :max adults.',
    'max_children' => ':room accepts at most :max children.',
    'child_without_adult' => 'A child cannot stay in a room without an adult.',
    'empty_room' => ':room is empty: every booked room needs at least one guest.',
    'shared_room' => ':room is selected twice: rooms cannot be shared.',
    'same_building' => 'All rooms of a booking must be in the same building.',
    'room_unavailable' => 'This room is not available.',
    'not_available_on' => ':room is not available on :date.',
    'children_not_accepted' => ':room does not accept children.',
    'min_child_age' => ':room accepts children from :age years.',
    'max_extra_beds' => ':room allows at most :max extra beds.',
    'max_cots' => ':room allows at most :max cots.',
    'min_stay' => ':room requires a minimum stay of :nights nights.',
    'max_stay' => ':room allows a maximum stay of :nights nights.',
    'child_ages' => 'Enter the age of every child.',
    'check_out_after' => 'Check-out must be after check-in.',
    'check_in_past' => 'Check-in cannot be in the past.',
    'too_many_rooms' => 'Choose at most :max rooms.',
    'option_unavailable' => 'Room option #:id is not available for this :product.',
    'option_quantity' => 'You can add at most :max × :name.',
    'option_stage' => 'Stage :stage: every guest must be in a room.',
    'option_cannot_fit' => 'Stage :stage: these rooms cannot fit :count guests, add a room.',
    'options_multi_day_only' => 'Rooms can only be added to multi-day products.',
    'trip_every_guest' => 'Every guest must be in a room.',
    'trip_cannot_fit' => 'These rooms cannot fit :count guests, add a room.',
];
