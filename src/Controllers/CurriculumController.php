<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Models\Curriculum;
use App\Services\DataStore;
use App\Services\ValidationService;
use App\Helpers\ResponseHelper;

class CurriculumController
{
    /**
     * Get all curriculums
     */
    public function index(Request $request, Response $response): Response
    {
        $curriculums = DataStore::getAllCurriculums();
        $formattedCurriculums = ResponseHelper::formatCollection($curriculums);

        return ResponseHelper::success($response, $formattedCurriculums);
    }

    /**
     * Get a specific curriculum
     */
    public function show(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $curriculum = DataStore::getCurriculum($id);

        if(!$curriculum){
            return ResponseHelper::notFound($response, 'Curriculum');
        }

        return ResponseHelper::success($response, ResponseHelper::formatModel($curriculum));
    }

    /**
     * Create a new curriculum
     */
    public function create(Request $request, Response $response): Response
    {
        $data = ValidationService::sanitizeInput($request->getParsedBody() ?? []);

        $curriculum = new Curriculum(
            DataStore::getNextId(),
            $data['name'] ?? '',
            $data['term'] ?? '',
            $data['yearLevel'] ?? 1,
            $data['subjectIds'] ?? []
        );

        // Set optional properties
        $curriculum->description = $data['description'] ?? '';
        $curriculum->prerequisites = $data['prerequisites'] ?? [];

        $errors = $curriculum->validate();
        if(!empty($errors)){
            return ResponseHelper::validationError($response, $errors);
        }

        // Validate that all subject IDs exist
        foreach($curriculum->subjectIds as $subjectId){
            if(!DataStore::getSubject($subjectId)){
                return ResponseHelper::validationError($response, ["Subject with ID {$subjectId} does not exist"]);
            }
        }

        // Calculate total units
        $subjects = DataStore::getAllSubjects();
        $curriculum->calculateTotalUnits($subjects);

        DataStore::addCurriculum($curriculum);

        return ResponseHelper::created(
            $response,
            ResponseHelper::formatModel($curriculum),
            'Curriculum created successfully'
        );
    }

    /**
     * Update an existing curriculum
     */
    public function update(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $data = ValidationService::sanitizeInput($request->getParsedBody() ?? []);

        $existingCurriculum = DataStore::getCurriculum($id);
        if(!$existingCurriculum){
            return ResponseHelper::notFound($response, 'Curriculum');
        }

        $curriculum = new Curriculum(
            $id,
            $data['name'] ?? $existingCurriculum->name,
            $data['term'] ?? $existingCurriculum->term,
            $data['yearLevel'] ?? $existingCurriculum->yearLevel,
            $data['subjectIds'] ?? $existingCurriculum->subjectIds
        );

        // Set optional properties
        $curriculum->description = $data['description'] ?? $existingCurriculum->description;
        $curriculum->prerequisites = $data['prerequisites'] ?? $existingCurriculum->prerequisites;

        $errors = $curriculum->validate();
        if(!empty($errors)){
            return ResponseHelper::validationError($response, $errors);
        }

        // Validate that all subject IDs exist
        foreach($curriculum->subjectIds as $subjectId){
            if(!DataStore::getSubject($subjectId)){
                return ResponseHelper::validationError($response, ["Subject with ID {$subjectId} does not exist"]);
            }
        }

        // Calculate total units
        $subjects = DataStore::getAllSubjects();
        $curriculum->calculateTotalUnits($subjects);

        DataStore::updateCurriculum($id, $curriculum);

        return ResponseHelper::success(
            $response,
            ResponseHelper::formatModel($curriculum),
            'Curriculum updated successfully'
        );
    }

    /**
     * Delete a curriculum
     */
    public function delete(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];

        if(!DataStore::getCurriculum($id)){
            return ResponseHelper::notFound($response, 'Curriculum');
        }

        // Check if curriculum is being used by any student
        $students = DataStore::getAllStudents();
        foreach($students as $student){
            if($student->curriculumId === $id){
                return ResponseHelper::error(
                    $response,
                    "Cannot delete curriculum. It is currently assigned to students",
                    'Constraint Violation',
                    409
                );
            }
        }

        DataStore::deleteCurriculum($id);

        return ResponseHelper::success($response, null, 'Curriculum deleted successfully');
    }

    /**
     * Get curriculums by term
     */
    public function getByTerm(Request $request, Response $response, array $args): Response
    {
        $term = $args['term'];
        $curriculums = DataStore::getAllCurriculums();

        $filteredCurriculums = array_filter($curriculums, function($curriculum) use ($term){
            return $curriculum->term === $term;
        });

        $formattedCurriculums = ResponseHelper::formatCollection(array_values($filteredCurriculums));

        return ResponseHelper::success($response, $formattedCurriculums);
    }

    /**
     * Add subjects to a curriculum
     */
    public function addSubjects(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $data = ValidationService::sanitizeInput($request->getParsedBody() ?? []);

        $curriculum = DataStore::getCurriculum($id);
        if(!$curriculum){
            return ResponseHelper::notFound($response, 'Curriculum');
        }

        $subjectIds = $data['subjectIds'] ?? [];
        if(!is_array($subjectIds) || empty($subjectIds)){
            return ResponseHelper::validationError($response, ['Subject IDs must be a non-empty array']);
        }

        // Validate that all subject IDs exist
        foreach($subjectIds as $subjectId){
            if(!DataStore::getSubject($subjectId)){
                return ResponseHelper::validationError($response, ["Subject with ID {$subjectId} does not exist"]);
            }
        }

        // Add subjects to curriculum
        foreach($subjectIds as $subjectId){
            $curriculum->addSubject($subjectId);
        }

        // Recalculate total units
        $subjects = DataStore::getAllSubjects();
        $curriculum->calculateTotalUnits($subjects);

        DataStore::updateCurriculum($id, $curriculum);

        return ResponseHelper::success(
            $response,
            ResponseHelper::formatModel($curriculum),
            'Subjects added to curriculum successfully'
        );
    }

    /**
     * Remove a subject from a curriculum
     */
    public function removeSubject(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $subjectId = (int)$args['subjectId'];

        $curriculum = DataStore::getCurriculum($id);
        if(!$curriculum){
            return ResponseHelper::notFound($response, 'Curriculum');
        }

        if(!$curriculum->hasSubject($subjectId)){
            return ResponseHelper::error(
                $response,
                'Subject is not part of this curriculum',
                'Invalid Operation',
                400
            );
        }

        // Check if removing this subject would leave curriculum with no subjects
        if(count($curriculum->subjectIds) <= 1){
            return ResponseHelper::error(
                $response,
                'Cannot remove subject. Curriculum must have at least one subject',
                'Constraint Violation',
                409
            );
        }

        $curriculum->removeSubject($subjectId);

        // Recalculate total units
        $subjects = DataStore::getAllSubjects();
        $curriculum->calculateTotalUnits($subjects);

        DataStore::updateCurriculum($id, $curriculum);

        return ResponseHelper::success(
            $response,
            ResponseHelper::formatModel($curriculum),
            'Subject removed from curriculum successfully'
        );
    }

    /**
     * Validate a curriculum
     */
    public function validate(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $curriculum = DataStore::getCurriculum($id);

        if(!$curriculum){
            return ResponseHelper::notFound($response, 'Curriculum');
        }

        $validationResults = [
            'isValid' => true,
            'errors' => [],
            'warnings' => [],
            'info' => [],
        ];

        // Basic validation
        $errors = $curriculum->validate();
        if(!empty($errors)){
            $validationResults['isValid'] = false;
            $validationResults['errors'] = array_merge($validationResults['errors'], $errors);
        }

        // Check if all subjects exist
        $subjects = DataStore::getAllSubjects();
        foreach($curriculum->subjectIds as $subjectId){
            if(!isset($subjects[$subjectId])){
                $validationResults['isValid'] = false;
                $validationResults['errors'][] = "Subject with ID {$subjectId} does not exist";
            }
        }

        // Check total units
        $curriculum->calculateTotalUnits($subjects);
        if($curriculum->totalUnits < 12){
            $validationResults['warnings'][] = "Total units ({$curriculum->totalUnits}) is below recommended minimum of 12";
        }
        elseif($curriculum->totalUnits > 24){
            $validationResults['warnings'][] = "Total units ({$curriculum->totalUnits}) exceeds recommended maximum of 24";
        }

        // Check for prerequisite validation
        foreach($curriculum->prerequisites as $prerequisiteId){
            if(!DataStore::getCurriculum($prerequisiteId)){
                $validationResults['warnings'][] = "Prerequisite curriculum with ID {$prerequisiteId} does not exist";
            }
        }

        // Add info
        $validationResults['info'][] = "Total subjects: " . count($curriculum->subjectIds);
        $validationResults['info'][] = "Total units: " . $curriculum->totalUnits;
        $validationResults['info'][] = "Prerequisites: " . count($curriculum->prerequisites);

        return ResponseHelper::success($response, $validationResults);
    }

    /**
     * Search curriculums
     */
    public function search(Request $request, Response $response): Response
    {
        $queryParams = $request->getQueryParams();
        $searchTerm = $queryParams['q'] ?? '';
        $term = $queryParams['term'] ?? '';
        $yearLevel = isset($queryParams['yearLevel']) ? (int)$queryParams['yearLevel'] : null;
        $minUnits = isset($queryParams['minUnits']) ? (int)$queryParams['minUnits'] : null;
        $maxUnits = isset($queryParams['maxUnits']) ? (int)$queryParams['maxUnits'] : null;

        $curriculums = DataStore::getAllCurriculums();
        $filteredCurriculums = array_filter($curriculums, function($curriculum) use ($searchTerm, $term, $yearLevel, $minUnits, $maxUnits){
            // Text search in name and description
            if(!empty($searchTerm)){
                $searchLower = strtolower($searchTerm);
                $nameMatch = strpos(strtolower($curriculum->name), $searchLower) !== false;
                $descMatch = strpos(strtolower($curriculum->description), $searchLower) !== false;
                if(!$nameMatch && !$descMatch){
                    return false;
                }
            }

            // Term filter
            if(!empty($term) && $curriculum->term !== $term){
                return false;
            }

            // Year level filter
            if($yearLevel !== null && $curriculum->yearLevel !== $yearLevel){
                return false;
            }

            // Units range filter
            if($minUnits !== null && $curriculum->totalUnits < $minUnits){
                return false;
            }

            if($maxUnits !== null && $curriculum->totalUnits > $maxUnits){
                return false;
            }

            return true;
        });

        $formattedCurriculums = ResponseHelper::formatCollection(array_values($filteredCurriculums));

        return ResponseHelper::success($response, [
            'curriculums' => $formattedCurriculums,
            'total' => count($filteredCurriculums),
            'filters' => [
                'searchTerm' => $searchTerm,
                'term' => $term,
                'yearLevel' => $yearLevel,
                'minUnits' => $minUnits,
                'maxUnits' => $maxUnits,
            ],
        ]);
    }

    /**
     * Get curriculum statistics
     */
    public function stats(Request $request, Response $response): Response
    {
        $curriculums = DataStore::getAllCurriculums();

        $stats = [
            'total' => count($curriculums),
            'totalUnits' => array_sum(array_map(fn($c) => $c->totalUnits, $curriculums)),
            'averageUnits' => count($curriculums) > 0 ? round(array_sum(array_map(fn($c) => $c->totalUnits, $curriculums)) / count($curriculums), 2) : 0,
            'distribution' => [
                'byTerm' => [],
                'byYearLevel' => [],
                'byUnits' => [],
                'bySubjectCount' => [],
            ],
        ];

        // Calculate distribution by term
        $termCounts = [];
        foreach($curriculums as $curriculum){
            $term = $curriculum->term;
            $termCounts[$term] = ($termCounts[$term] ?? 0) + 1;
        }
        $stats['distribution']['byTerm'] = $termCounts;

        // Calculate distribution by year level
        $yearLevelCounts = [];
        foreach($curriculums as $curriculum){
            $yearLevel = $curriculum->yearLevel;
            $yearLevelCounts[$yearLevel] = ($yearLevelCounts[$yearLevel] ?? 0) + 1;
        }
        ksort($yearLevelCounts);
        $stats['distribution']['byYearLevel'] = $yearLevelCounts;

        // Calculate distribution by units ranges
        $unitsRanges = [
            '0-12' => 0,
            '13-18' => 0,
            '19-24' => 0,
            '25+' => 0,
        ];

        foreach($curriculums as $curriculum){
            if($curriculum->totalUnits <= 12){
                $unitsRanges['0-12']++;
            }
            elseif($curriculum->totalUnits <= 18){
                $unitsRanges['13-18']++;
            }
            elseif($curriculum->totalUnits <= 24){
                $unitsRanges['19-24']++;
            }
            else{
                $unitsRanges['25+']++;
            }
        }
        $stats['distribution']['byUnits'] = $unitsRanges;

        // Calculate distribution by subject count
        $subjectCountDistribution = [];
        foreach($curriculums as $curriculum){
            $count = count($curriculum->subjectIds);
            $subjectCountDistribution[$count] = ($subjectCountDistribution[$count] ?? 0) + 1;
        }
        ksort($subjectCountDistribution);
        $stats['distribution']['bySubjectCount'] = $subjectCountDistribution;

        return ResponseHelper::success($response, $stats);
    }
}