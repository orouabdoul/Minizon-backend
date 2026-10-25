<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BoardingEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'booking_uuid',
        'trip_uuid',
        'driver_id',
        'passenger_id',
        'event_type',
        'driver_lat',
        'driver_lng',
        'passenger_lat',
        'passenger_lng',
        'distance_meters',
        'notes',
    ];

    protected $casts = [
        'driver_lat'       => 'float',
        'driver_lng'       => 'float',
        'passenger_lat'    => 'float',
        'passenger_lng'    => 'float',
        'distance_meters'  => 'float',
        'created_at'       => 'datetime',
    ];

    public static function log(
        string  $bookingUuid,
        string  $tripUuid,
        string  $eventType,
        ?int    $driverId    = null,
        ?int    $passengerId = null,
        ?float  $driverLat   = null,
        ?float  $driverLng   = null,
        ?float  $passengerLat = null,
        ?float  $passengerLng = null,
        ?string $notes        = null,
    ): void {
        $distanceMeters = null;
        if ($driverLat && $driverLng && $passengerLat && $passengerLng) {
            $distanceMeters = self::haversineMeters(
                $driverLat, $driverLng, $passengerLat, $passengerLng
            );
        }

        try {
            self::create([
                'booking_uuid'   => $bookingUuid,
                'trip_uuid'      => $tripUuid,
                'driver_id'      => $driverId,
                'passenger_id'   => $passengerId,
                'event_type'     => $eventType,
                'driver_lat'     => $driverLat,
                'driver_lng'     => $driverLng,
                'passenger_lat'  => $passengerLat,
                'passenger_lng'  => $passengerLng,
                'distance_meters'=> $distanceMeters,
                'notes'          => $notes,
            ]);
        } catch (\Throwable) {
            // Log non bloquant — ne doit jamais faire échouer le flux principal
        }
    }

    private static function haversineMeters(
        float $lat1, float $lng1, float $lat2, float $lng2
    ): float {
        $r    = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a    = sin($dLat / 2) ** 2
              + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        return (float) round($r * 2 * atan2(sqrt($a), sqrt(1 - $a)), 2);
    }
}
