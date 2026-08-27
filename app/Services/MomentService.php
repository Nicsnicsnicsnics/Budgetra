<?php
namespace App\Services;

use App\Models\Moment;
use App\Models\MomentPhoto;
use App\Models\Trip;
use Illuminate\Support\Facades\Storage;

class MomentService
{
    public function createPin(Trip $trip, array $data, array $photoUploads): Moment
    {
        $moment = Moment::create([
            'trip_id'      => $trip->id,
            'place_name'   => $data['place_name'],
            'description'  => $data['description'] ?: null,
            'visited_date' => $data['visited_date'],
            'lat'          => $data['lat'],
            'lng'          => $data['lng'],
        ]);

        $this->attachPhotos($moment, $photoUploads);

        return $moment->load('photos');
    }

    public function updatePin(Moment $moment, array $data, array $photoUploads): Moment
    {
        $moment->update([
            'place_name'   => $data['place_name'],
            'description'  => $data['description'] ?: null,
            'visited_date' => $data['visited_date'],
            'lat'          => $data['lat'],
            'lng'          => $data['lng'],
        ]);

        $this->attachPhotos($moment, $photoUploads);

        return $moment->load('photos');
    }

    private function attachPhotos(Moment $moment, array $photoUploads): void
    {
        foreach ($photoUploads as $upload) {
            MomentPhoto::create([
                'moment_id'  => $moment->id,
                'photo_path' => $upload->store('moment-photos', 'public'),
            ]);
        }
    }

    public function deletePhoto(MomentPhoto $photo): void
    {
        Storage::disk('public')->delete($photo->photo_path);
        $photo->delete();
    }

    public function deleteMoment(Moment $moment): void
    {
        foreach ($moment->photos as $photo) {
            Storage::disk('public')->delete($photo->photo_path);
        }
        $moment->delete(); // cascade-deletes moment_photos rows via the FK
    }

    public function pinToArray(Moment $moment): array
    {
        return [
            'id'                => $moment->id,
            'trip_id'           => $moment->trip_id,
            'lat'               => (float) $moment->lat,
            'lng'               => (float) $moment->lng,
            'place_name'        => $moment->place_name,
            'description'       => $moment->description,
            'visited_date'      => $moment->visited_date->format('M j, Y'),
            'visited_date_sort' => $moment->visited_date->toDateString(),
            'photo_urls'        => $moment->photos->map(
                fn (MomentPhoto $p) => Storage::disk('public')->url($p->photo_path)
            )->values()->toArray(),
        ];
    }

    public function existingPhotosArray(Moment $moment): array
    {
        return $moment->photos->map(fn (MomentPhoto $p) => [
            'id'  => $p->id,
            'url' => Storage::disk('public')->url($p->photo_path),
        ])->values()->toArray();
    }
}
