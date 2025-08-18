<?php
$dataFile = __DIR__ . '/../storage/geofence.json';
if (!file_exists($dataFile)) {
    throw new Exception("Geofence not set");
}
class GeoFencer
{
    public function isWithinGeofence($userLat, $userLng)
    {
        $data = json_decode(file_get_contents(__DIR__ . '/../storage/geofence.json'), true);

        $centerLat = $data['lat'];
        $centerLng = $data['lng'];
        $radius = $data['radius'];

        $distance = $this->calculateDistance($centerLat, $centerLng, $userLat, $userLng);
        return $distance <= $radius;
    }

    private function calculateDistance($lat1, $lng1, $lat2, $lng2)
    {
        $earthRadius = 6371000; // meters

        $latFrom = deg2rad($lat1);
        $lngFrom = deg2rad($lng1);
        $latTo = deg2rad($lat2);
        $lngTo = deg2rad($lng2);

        $latDelta = $latTo - $latFrom;
        $lngDelta = $lngTo - $lngFrom;

        $angle = 2 * asin(sqrt(pow(sin($latDelta / 2), 2) +
            cos($latFrom) * cos($latTo) * pow(sin($lngDelta / 2), 2)));
        return $earthRadius * $angle;
    }
}
