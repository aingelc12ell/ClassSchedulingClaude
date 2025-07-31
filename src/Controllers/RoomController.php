<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Models\Room;
use App\Services\DataStore;
use App\Services\ValidationService;
use App\Helpers\ResponseHelper;

class RoomController
{
    /**
     * Get all rooms
     */
    public function index(Request $request, Response $response): Response
    {
        $rooms = DataStore::getAllRooms();
        $formattedRooms = ResponseHelper::formatCollection($rooms);

        return ResponseHelper::success($response, $formattedRooms);
    }

    /**
     * Get a specific room
     */
    public function show(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $room = DataStore::getRoom($id);

        if(!$room){
            return ResponseHelper::notFound($response, 'Room');
        }

        return ResponseHelper::success($response, ResponseHelper::formatModel($room));
    }

    /**
     * Create a new room
     */
    public function create(Request $request, Response $response): Response
    {
        $data = ValidationService::sanitizeInput($request->getParsedBody() ?? []);

        $room = new Room(
            DataStore::getNextId(),
            $data['name'] ?? '',
            $data['capacity'] ?? 0,
            $data['location'] ?? '',
            $data['equipment'] ?? []
        );

        $errors = $room->validate();
        if(!empty($errors)){
            return ResponseHelper::validationError($response, $errors);
        }

        DataStore::addRoom($room);

        return ResponseHelper::created(
            $response,
            ResponseHelper::formatModel($room),
            'Room created successfully'
        );
    }

    /**
     * Update an existing room
     */
    public function update(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $data = ValidationService::sanitizeInput($request->getParsedBody() ?? []);

        $existingRoom = DataStore::getRoom($id);
        if(!$existingRoom){
            return ResponseHelper::notFound($response, 'Room');
        }

        $room = new Room(
            $id,
            $data['name'] ?? $existingRoom->name,
            $data['capacity'] ?? $existingRoom->capacity,
            $data['location'] ?? $existingRoom->location,
            $data['equipment'] ?? $existingRoom->equipment
        );

        $errors = $room->validate();
        if(!empty($errors)){
            return ResponseHelper::validationError($response, $errors);
        }

        DataStore::updateRoom($id, $room);

        return ResponseHelper::success(
            $response,
            ResponseHelper::formatModel($room),
            'Room updated successfully'
        );
    }

    /**
     * Delete a room
     */
    public function delete(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];

        if(!DataStore::getRoom($id)){
            return ResponseHelper::notFound($response, 'Room');
        }

        // Check if room is being used in any class
        $classes = DataStore::findClassesByRoom($id);
        if(!empty($classes)){
            return ResponseHelper::error(
                $response,
                "Cannot delete room. It is currently used in active classes",
                'Constraint Violation',
                409
            );
        }

        DataStore::deleteRoom($id);

        return ResponseHelper::success($response, null, 'Room deleted successfully');
    }

    /**
     * Get rooms by minimum capacity
     */
    public function getByCapacity(Request $request, Response $response, array $args): Response
    {
        $minCapacity = (int)$args['minCapacity'];
        $rooms = DataStore::getAllRooms();

        $filteredRooms = array_filter($rooms, function($room) use ($minCapacity){
            return $room->capacity >= $minCapacity;
        });

        $formattedRooms = ResponseHelper::formatCollection(array_values($filteredRooms));

        return ResponseHelper::success($response, $formattedRooms);
    }

    /**
     * Search rooms
     */
    public function search(Request $request, Response $response): Response
    {
        $queryParams = $request->getQueryParams();
        $searchTerm = $queryParams['q'] ?? '';
        $minCapacity = isset($queryParams['minCapacity']) ? (int)$queryParams['minCapacity'] : null;
        $maxCapacity = isset($queryParams['maxCapacity']) ? (int)$queryParams['maxCapacity'] : null;
        $location = $queryParams['location'] ?? '';
        $equipment = $queryParams['equipment'] ?? '';

        $rooms = DataStore::getAllRooms();
        $filteredRooms = array_filter($rooms, function($room) use ($searchTerm, $minCapacity, $maxCapacity, $location, $equipment){
            // Text search in name
            if(!empty($searchTerm)){
                $searchLower = strtolower($searchTerm);
                $nameMatch = strpos(strtolower($room->name), $searchLower) !== false;
                if(!$nameMatch){
                    return false;
                }
            }

            // Capacity range filter
            if($minCapacity !== null && $room->capacity < $minCapacity){
                return false;
            }

            if($maxCapacity !== null && $room->capacity > $maxCapacity){
                return false;
            }

            // Location filter
            if(!empty($location)){
                $locationLower = strtolower($location);
                $locationMatch = strpos(strtolower($room->location), $locationLower) !== false;
                if(!$locationMatch){
                    return false;
                }
            }

            // Equipment filter
            if(!empty($equipment)){
                if(!$room->hasEquipment($equipment)){
                    return false;
                }
            }

            return true;
        });

        $formattedRooms = ResponseHelper::formatCollection(array_values($filteredRooms));

        return ResponseHelper::success($response, [
            'rooms' => $formattedRooms,
            'total' => count($filteredRooms),
            'filters' => [
                'searchTerm' => $searchTerm,
                'minCapacity' => $minCapacity,
                'maxCapacity' => $maxCapacity,
                'location' => $location,
                'equipment' => $equipment,
            ],
        ]);
    }

    /**
     * Get room statistics
     */
    public function stats(Request $request, Response $response): Response
    {
        $rooms = DataStore::getAllRooms();

        $stats = [
            'total' => count($rooms),
            'totalCapacity' => array_sum(array_map(fn($r) => $r->capacity, $rooms)),
            'averageCapacity' => count($rooms) > 0 ? round(array_sum(array_map(fn($r) => $r->capacity, $rooms)) / count($rooms), 2) : 0,
            'distribution' => [
                'byCapacity' => [],
                'byLocation' => [],
                'byEquipment' => [],
            ],
        ];

        // Calculate distribution by capacity ranges
        $capacityRanges = [
            '1-20' => 0,
            '21-50' => 0,
            '51-100' => 0,
            '101-200' => 0,
            '200+' => 0,
        ];

        foreach($rooms as $room){
            if($room->capacity <= 20){
                $capacityRanges['1-20']++;
            }
            elseif($room->capacity <= 50){
                $capacityRanges['21-50']++;
            }
            elseif($room->capacity <= 100){
                $capacityRanges['51-100']++;
            }
            elseif($room->capacity <= 200){
                $capacityRanges['101-200']++;
            }
            else{
                $capacityRanges['200+']++;
            }
        }
        $stats['distribution']['byCapacity'] = $capacityRanges;

        // Calculate distribution by location
        $locationCounts = [];
        foreach($rooms as $room){
            $location = $room->location ?: 'Unknown';
            $locationCounts[$location] = ($locationCounts[$location] ?? 0) + 1;
        }
        $stats['distribution']['byLocation'] = $locationCounts;

        // Calculate equipment statistics
        $equipmentCounts = [];
        foreach($rooms as $room){
            foreach($room->equipment as $item){
                $equipmentCounts[$item] = ($equipmentCounts[$item] ?? 0) + 1;
            }
        }
        arsort($equipmentCounts);
        $stats['distribution']['byEquipment'] = $equipmentCounts;

        return ResponseHelper::success($response, $stats);
    }
}