# Academic Scheduling API Documentation

## Overview

The Academic Scheduling API is a RESTful service designed to manage academic resources and generate conflict-free class schedules. It handles Students, Rooms, Subjects, Curriculums, Teachers, and Classes with sophisticated scheduling algorithms.

## Base URL
```
http://localhost:8000
```

## Authentication
Currently, no authentication is required. This should not be implemented in production.

## Response Format

All responses follow a consistent JSON structure:

### Success Response
```json
{
    "success": true,
    "message": "Success message",
    "data": { /* response data */ },
    "timestamp": "2024-01-01T00:00:00+00:00"
}
```

### Error Response
```json
{
    "success": false,
    "message": "Error message",
    "errors": ["Error details"],
    "timestamp": "2024-01-01T00:00:00+00:00"
}
```

## Endpoints

### Root Endpoint
- **GET /** - API information and available endpoints

### Subjects

#### List all subjects
- **GET /subjects**
- **Response**: Array of subject objects

#### Get specific subject
- **GET /subjects/{id}**
- **Response**: Subject object

#### Create subject
- **POST /subjects**
- **Request Body**:
```json
{
    "title": "Mathematics 101",
    "units": 3,
    "hoursPerWeek": 4
}
```

#### Update subject
- **PUT /subjects/{id}**
- **Request Body**: Same as create

#### Delete subject
- **DELETE /subjects/{id}**

#### Search subjects
- **GET /subjects/search?q={query}&units={units}&minHours={min}&maxHours={max}**

#### Get subject statistics
- **GET /subjects/stats**

#### Get subjects by curriculum
- **GET /subjects/curriculum/{curriculumId}**

### Rooms

#### List all rooms
- **GET /rooms**

#### Get specific room
- **GET /rooms/{id}**

#### Create room
- **POST /rooms**
- **Request Body**:
```json
{
    "name": "Room A101",
    "capacity": 30,
    "location": "Building A, Floor 1",
    "equipment": ["projector", "whiteboard"]
}
```

#### Update room
- **PUT /rooms/{id}**

#### Delete room
- **DELETE /rooms/{id}**

#### Search rooms
- **GET /rooms/search?q={query}&minCapacity={min}&equipment={equipment}**

#### Get rooms by capacity
- **GET /rooms/capacity/{minCapacity}**

### Teachers

#### List all teachers
- **GET /teachers**

#### Get specific teacher
- **GET /teachers/{id}**

#### Create teacher
- **POST /teachers**
- **Request Body**:
```json
{
    "name": "Dr. John Smith",
    "email": "john.smith@university.edu",
    "subjectIds": [1, 2, 3],
    "maxHoursPerWeek": 40
}
```

#### Update teacher
- **PUT /teachers/{id}**

#### Delete teacher
- **DELETE /teachers/{id}**

#### Get teachers by subject
- **GET /teachers/subject/{subjectId}**

#### Add subjects to teacher
- **POST /teachers/{id}/subjects**
- **Request Body**:
```json
{
    "subjectIds": [4, 5]
}
```

#### Remove subject from teacher
- **DELETE /teachers/{id}/subjects/{subjectId}**

### Students

#### List all students
- **GET /students**

#### Get specific student
- **GET /students/{id}**

#### Create student
- **POST /students**
- **Request Body**:
```json
{
    "name": "Jane Doe",
    "email": "jane.doe@student.edu",
    "studentNumber": "ST2024001",
    "curriculumId": 1,
    "yearLevel": 2
}
```

#### Update student
- **PUT /students/{id}**

#### Delete student
- **DELETE /students/{id}**

#### Get students by curriculum
- **GET /students/curriculum/{curriculumId}**

#### Enroll student in class
- **POST /students/{id}/enroll**
- **Request Body**:
```json
{
    "classId": 1
}
```

#### Drop student from class
- **DELETE /students/{id}/classes/{classId}**

### Curriculums

#### List all curriculums
- **GET /curriculums**

#### Get specific curriculum
- **GET /curriculums/{id}**

#### Create curriculum
- **POST /curriculums**
- **Request Body**:
```json
{
    "name": "Computer Science Year 1",
    "term": "1st Semester",
    "yearLevel": 1,
    "subjectIds": [1, 2, 3],
    "description": "First year computer science curriculum"
}
```

#### Update curriculum
- **PUT /curriculums/{id}**

#### Delete curriculum
- **DELETE /curriculums/{id}**

#### Get curriculums by term
- **GET /curriculums/term/{term}**

#### Add subjects to curriculum
- **POST /curriculums/{id}/subjects**
- **Request Body**:
```json
{
    "subjectIds": [4, 5]
}
```

#### Remove subject from curriculum
- **DELETE /curriculums/{id}/subjects/{subjectId}**

#### Validate curriculum
- **GET /curriculums/{id}/validate**

### Classes

#### List all classes
- **GET /classes**

#### Get specific class
- **GET /classes/{id}**

#### Create class
- **POST /classes**
- **Request Body**:
```json
{
    "code": "MATH101-A-2024",
    "subjectId": 1,
    "teacherId": 1,
    "roomId": 1,
    "maxStudents": 30,
    "term": "1st Semester"
}
```

#### Update class
- **PUT /classes/{id}**

#### Delete class
- **DELETE /classes/{id}**

#### Get classes by subject/teacher/room
- **GET /classes/subject/{subjectId}**
- **GET /classes/teacher/{teacherId}**
- **GET /classes/room/{roomId}**

#### Enroll students in class
- **POST /classes/{id}/students**
- **Request Body**:
```json
{
    "studentIds": [1, 2, 3]
}
```

#### Remove student from class
- **DELETE /classes/{id}/students/{studentId}**

### Schedules

#### Generate schedule
- **POST /schedules/generate**
- **Request Body**:
```json
{
    "curriculumId": 1,
    "studentCount": 25,
    "preferences": {
        "preferredDays": ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday"],
        "maxHoursPerDay": 4,
        "preferredTeachers": [1, 2],
        "preferredRooms": [1, 2, 3],
        "term": "1st Semester"
    }
}
```

#### List all schedules
- **GET /schedules**
- **Query Parameters**: page, limit, term, teacherId, roomId

#### Get class schedule
- **GET /schedules/class/{classId}**

#### Get teacher schedule
- **GET /schedules/teacher/{teacherId}**

#### Get room schedule
- **GET /schedules/room/{roomId}**

#### Get curriculum schedule
- **GET /schedules/curriculum/{curriculumId}**

#### Validate schedule
- **POST /schedules/validate**
- **Request Body**:
```json
{
    "schedules": [
        {
            "classId": 1,
            "teacher": {"id": 1},
            "room": {"id": 1},
            "schedule": [
                {
                    "day": "Monday",
                    "startTime": "09:00",
                    "endTime": "10:00"
                }
            ]
        }
    ]
}
```

#### Get available time slots
- **GET /schedules/timeslots**

#### Get optimization suggestions
- **GET /schedules/optimize**

## Schedule Generation Algorithm

The schedule generation process follows these steps:

1. **Validation**: Validate curriculum integrity and resource availability
2. **Resource Allocation**: Find available teachers and rooms for each subject
3. **Time Slot Assignment**: Distribute hours across working days
4. **Conflict Detection**: Check for teacher, room, and student conflicts
5. **Optimization**: Generate suggestions for better resource utilization

### Constraints

- Teachers can only teach subjects they're qualified for
- Room capacity must accommodate student count
- No teacher can be in two places at once
- No room can host multiple classes simultaneously
- Students in same curriculum cannot have conflicting schedules
- Maximum hours per day limits are respected
- Business hours (7:00 AM - 10:00 PM) are enforced

### Schedule Response Format

```json
{
    "success": true,
    "data": {
        "schedules": [
            {
                "classId": 1,
                "classCode": "MATH101-A-2024",
                "subject": {
                    "id": 1,
                    "title": "Mathematics 101",
                    "units": 3,
                    "hoursPerWeek": 4
                },
                "teacher": {
                    "id": 1,
                    "name": "Dr. John Smith",
                    "email": "john.smith@university.edu"
                },
                "room": {
                    "id": 1,
                    "name": "Room A101",
                    "capacity": 30,
                    "location": "Building A, Floor 1"
                },
                "schedule": [
                    {
                        "day": "Monday",
                        "startTime": "09:00",
                        "endTime": "11:00"
                    },
                    {
                        "day": "Wednesday",
                        "startTime": "14:00",
                        "endTime": "16:00"
                    }
                ],
                "maxStudents": 30,
                "totalHours": 4
            }
        ],
        "summary": {
            "totalSchedules": 5,
            "totalConflicts": 0,
            "totalWarnings": 1
        }
    },
    "conflicts": [],
    "warnings": [
        "Class MATH101-A-2024 is spread across too many days, consider consolidation"
    ]
}
```

## Error Codes

- **400 Bad Request**: Invalid request data
- **401 Unauthorized**: Authentication required
- **403 Forbidden**: Insufficient permissions
- **404 Not Found**: Resource not found
- **409 Conflict**: Scheduling conflicts or constraint violations
- **422 Unprocessable Entity**: Validation errors
- **500 Internal Server Error**: Server error

## Rate Limiting

Currently not implemented. Should be added in production.

## Pagination

All list endpoints support pagination:
- `page`: Page number (default: 1)
- `limit`: Items per page (default: 10, max: 100)

Response includes pagination metadata:
```json
{
    "data": [...],
    "pagination": {
        "page": 1,
        "limit": 10,
        "total": 100,
        "pages": 10,
        "hasNext": true,
        "hasPrev": false
    }
}
```

## Health Check

- **GET /health**: System health and statistics

## Examples

### Complete Workflow Example

1. **Create Subjects**:
```bash
curl -X POST http://localhost:8000/subjects \
  -H "Content-Type: application/json" \
  -d '{"title": "Mathematics 101", "units": 3, "hoursPerWeek": 4}'
```

2. **Create Rooms**:
```bash
curl -X POST http://localhost:8000/rooms \
  -H "Content-Type: application/json" \
  -d '{"name": "Room A101", "capacity": 30, "location": "Building A"}'
```

3. **Create Teachers**:
```bash
curl -X POST http://localhost:8000/teachers \
  -H "Content-Type: application/json" \
  -d '{"name": "Dr. Smith", "email": "smith@edu", "subjectIds": [1]}'
```

4. **Create Curriculum**:
```bash
curl -X POST http://localhost:8000/curriculums \
  -H "Content-Type: application/json" \
  -d '{"name": "CS Year 1", "term": "1st Semester", "yearLevel": 1, "subjectIds": [1]}'
```

5. **Generate Schedule**:
```bash
curl -X POST http://localhost:8000/schedules/generate \
  -H "Content-Type: application/json" \
  -d '{"curriculumId": 1, "studentCount": 25}'
```

## Development Notes

- In-memory storage is used for demo purposes
- Replace DataStore with database implementation for production
- Add authentication and authorization
- Implement rate limiting
- Add comprehensive logging
- Consider caching for performance
- Add data persistence
