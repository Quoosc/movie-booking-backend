# User Management Endpoints - Response Format Reference

## 1. GET /api/users - List All Users (Admin Only)

**Authentication**: JWT Bearer Token (auth.jwt middleware)  
**HTTP Method**: GET  
**Status Code**: 200 OK

**Request**:
```
GET /api/users
Authorization: Bearer <accessToken>
```

**Response**:
```json
{
  "code": 200,
  "message": "OK",
  "data": [
    {
      "userId": "3fa85f64-5717-4562-b3fc-2c963f66afa6",
      "email": "user@gmail.com",
      "username": "johndoe",
      "phoneNumber": "0912345678",
      "avatarUrl": "https://cdn.example.com/avatars/johndoe.jpg",
      "avatarCloudinaryId": "cloud-abc123",
      "loyaltyPoints": 120,
      "membershipTier": {
        "membershipTierId": "11111111-1111-1111-1111-111111111111",
        "name": "Silver",
        "minPoints": 100,
        "discountType": "PERCENTAGE",
        "discountValue": 5.0,
        "description": "Silver tier benefits",
        "isActive": true,
        "createdAt": "2025-01-01T00:00:00Z",
        "updatedAt": "2025-11-01T12:00:00Z"
      },
      "createdAt": "2025-01-01T10:00:00Z",
      "updatedAt": "2025-11-24T15:35:27.490Z"
    },
    {
      "userId": "4gb96g75-6828-5673-c4gd-3d074g76bg17",
      "email": "anotheruser@gmail.com",
      "username": "janedoe",
      "phoneNumber": "0912345679",
      "avatarUrl": "https://cdn.example.com/avatars/janedoe.jpg",
      "avatarCloudinaryId": "cloud-def456",
      "loyaltyPoints": 250,
      "membershipTier": {
        "membershipTierId": "22222222-2222-2222-2222-222222222222",
        "name": "Gold",
        "minPoints": 200,
        "discountType": "PERCENTAGE",
        "discountValue": 10.0,
        "description": "Gold tier benefits",
        "isActive": true,
        "createdAt": "2025-01-01T00:00:00Z",
        "updatedAt": "2025-11-01T12:00:00Z"
      },
      "createdAt": "2025-01-02T10:00:00Z",
      "updatedAt": "2025-11-25T10:00:00Z"
    }
  ]
}
```

---

## 2. GET /api/users/{userId} - Get User by ID (Admin Only)

**Authentication**: JWT Bearer Token (auth.jwt middleware)  
**HTTP Method**: GET  
**Status Code**: 200 OK | 404 Not Found

**Request**:
```
GET /api/users/3fa85f64-5717-4562-b3fc-2c963f66afa6
Authorization: Bearer <accessToken>
```

**Response (200 OK)**:
```json
{
  "code": 200,
  "message": "OK",
  "data": {
    "userId": "3fa85f64-5717-4562-b3fc-2c963f66afa6",
    "email": "user@gmail.com",
    "username": "johndoe",
    "phoneNumber": "0912345678",
    "avatarUrl": "https://cdn.example.com/avatars/johndoe.jpg",
    "avatarCloudinaryId": "cloud-abc123",
    "loyaltyPoints": 120,
    "membershipTier": {
      "membershipTierId": "11111111-1111-1111-1111-111111111111",
      "name": "Silver",
      "minPoints": 100,
      "discountType": "PERCENTAGE",
      "discountValue": 5.0,
      "description": "Silver tier benefits",
      "isActive": true,
      "createdAt": "2025-01-01T00:00:00Z",
      "updatedAt": "2025-11-01T12:00:00Z"
    },
    "createdAt": "2025-01-01T10:00:00Z",
    "updatedAt": "2025-11-24T15:35:27.490Z"
  }
}
```

**Response (404 Not Found)**:
```json
{
  "code": 404,
  "message": "User not found",
  "data": null
}
```

---

## 3. PATCH /api/users/{userId}/role - Update User Role (Admin Only)

**Authentication**: JWT Bearer Token (auth.jwt middleware)  
**HTTP Method**: PATCH  
**Status Code**: 200 OK | 404 Not Found | 400 Bad Request

**Request**:
```
PATCH /api/users/3fa85f64-5717-4562-b3fc-2c963f66afa6/role
Authorization: Bearer <accessToken>
Content-Type: application/json

{
  "role": "ADMIN"
}
```

**Valid Role Values**:
- `USER` - Regular user
- `ADMIN` - Administrator with access to admin endpoints

**Response (200 OK)**:
```json
{
  "code": 200,
  "message": "OK",
  "data": {
    "userId": "3fa85f64-5717-4562-b3fc-2c963f66afa6",
    "email": "user@gmail.com",
    "username": "johndoe",
    "phoneNumber": "0912345678",
    "avatarUrl": "https://cdn.example.com/avatars/johndoe.jpg",
    "avatarCloudinaryId": "cloud-abc123",
    "loyaltyPoints": 120,
    "membershipTier": {
      "membershipTierId": "11111111-1111-1111-1111-111111111111",
      "name": "Silver",
      "minPoints": 100,
      "discountType": "PERCENTAGE",
      "discountValue": 5.0,
      "description": "Silver tier benefits",
      "isActive": true,
      "createdAt": "2025-01-01T00:00:00Z",
      "updatedAt": "2025-11-01T12:00:00Z"
    },
    "createdAt": "2025-01-01T10:00:00Z",
    "updatedAt": "2025-11-24T15:35:27.490Z"
  }
}
```

**Response (404 Not Found)**:
```json
{
  "code": 404,
  "message": "User not found",
  "data": null
}
```

**Response (400 Bad Request)**:
```json
{
  "code": 400,
  "message": "Invalid request",
  "data": {
    "role": ["The role must be one of: USER, ADMIN"]
  }
}
```

---

## 4. DELETE /api/users/{userId} - Delete User (Admin Only)

**Authentication**: JWT Bearer Token (auth.jwt middleware)  
**HTTP Method**: DELETE  
**Status Code**: 200 OK | 404 Not Found

**Request**:
```
DELETE /api/users/3fa85f64-5717-4562-b3fc-2c963f66afa6
Authorization: Bearer <accessToken>
```

**Response (200 OK)**:
```json
{
  "code": 200,
  "message": "OK",
  "data": "User deleted successfully"
}
```

**Response (404 Not Found)**:
```json
{
  "code": 404,
  "message": "User not found",
  "data": null
}
```

---

## Cinema Room Endpoints - Response Format Reference

## 1. POST /api/cinemas/rooms - Add Room to Cinema (Admin Only)

**Authentication**: JWT Bearer Token (auth.jwt middleware)  
**HTTP Method**: POST  
**Status Code**: 201 Created | 400 Bad Request

**Request**:
```json
{
  "cinemaId": "cinema-1111-aaaa-2222",
  "roomType": "IMAX",
  "roomNumber": 1
}
```

**Response (201 Created)**:
```json
{
  "code": 201,
  "message": "OK",
  "data": {
    "roomId": "room-aaa1-1111",
    "cinemaId": "cinema-1111-aaaa-2222",
    "roomType": "IMAX",
    "roomNumber": 1
  }
}
```

---

## 2. PUT /api/cinemas/rooms/{roomId} - Update Room (Admin Only)

**Authentication**: JWT Bearer Token (auth.jwt middleware)  
**HTTP Method**: PUT  
**Status Code**: 200 OK | 404 Not Found

**Request**:
```json
{
  "roomType": "IMAX",
  "roomNumber": 1
}
```

**Response (200 OK)**:
```json
{
  "code": 200,
  "message": "OK",
  "data": {
    "roomId": "room-aaa1-1111",
    "cinemaId": "cinema-1111-aaaa-2222",
    "roomType": "IMAX",
    "roomNumber": 1
  }
}
```

---

## Cinema Snacks Endpoints - Response Format Reference

## 1. POST /api/cinemas/snacks - Add Snack (Admin Only)

**Authentication**: JWT Bearer Token (auth.jwt middleware)  
**HTTP Method**: POST  
**Status Code**: 201 Created | 400 Bad Request

**Request**:
```json
{
  "cinemaId": "3e4a8c9f-1234-5678-90ab-cdef12345678",
  "name": "Popcorn Combo",
  "description": "Large popcorn + 2 drinks",
  "price": 120000.00,
  "type": "COMBO",
  "imageUrl": "https://cdn.example.com/popcorn-combo.jpg",
  "imageCloudinaryId": "snacks/popcorn_combo_abc123"
}
```

**Response (201 Created)**:
```json
{
  "code": 201,
  "message": "OK",
  "data": {
    "snackId": "88888888-8888-8888-8888-888888888888",
    "cinemaId": "3e4a8c9f-1234-5678-90ab-cdef12345678",
    "name": "Popcorn Combo",
    "description": "Large popcorn + 2 drinks",
    "price": 120000.00,
    "type": "COMBO",
    "imageUrl": "https://cdn.example.com/popcorn-combo.jpg",
    "imageCloudinaryId": "snacks/popcorn_combo_abc123"
  }
}
```

---

## 2. PUT /api/cinemas/snacks/{snackId} - Update Snack (Admin Only)

**Authentication**: JWT Bearer Token (auth.jwt middleware)  
**HTTP Method**: PUT  
**Status Code**: 200 OK | 404 Not Found

**Request**:
```json
{
  "name": "Mega Popcorn Combo",
  "description": "Extra large popcorn + 3 drinks",
  "price": 150000.00,
  "type": "COMBO",
  "imageUrl": "https://cdn.example.com/mega-popcorn-combo.jpg",
  "imageCloudinaryId": "snacks/mega_popcorn_combo_def456"
}
```

**Response (200 OK)**:
```json
{
  "code": 200,
  "message": "OK",
  "data": {
    "snackId": "2c3d4e5f-6a7b-8c9d-0e1f-2a3b4c5d6e7f",
    "cinemaId": "3e4a8c9f-1234-5678-90ab-cdef12345678",
    "name": "Mega Popcorn Combo",
    "description": "Extra large popcorn + 3 drinks",
    "price": 150000.00,
    "type": "COMBO",
    "imageUrl": "https://cdn.example.com/mega-popcorn-combo.jpg",
    "imageCloudinaryId": "snacks/mega_popcorn_combo_def456"
  }
}
```

---

## Implementation Notes

### Field Mapping

| Request Field | Database Column | Note |
|--------------|-----------------|------|
| `userId` | `user_id` | UUID |
| `email` | `email` | Unique |
| `username` | `username` | Optional |
| `phoneNumber` | `phone_number` | Optional |
| `avatarUrl` | `avatar_url` | Optional |
| `avatarCloudinaryId` | `avatar_cloudinary_id` | Cloudinary ID |
| `loyaltyPoints` | `loyalty_points` | Integer |
| `role` | `role` | USER or ADMIN |
| `createdAt` | `created_at` | ISO 8601 |
| `updatedAt` | `updated_at` | ISO 8601 |

### Validation Rules

**User Role Update**:
- Role must be one of: `USER`, `ADMIN`
- Only admin users can change roles

**Cinema Room**:
- `cinemaId` must exist
- `roomNumber` must be >= 1
- `roomType` can be: STANDARD, IMAX, 3D, 4DX, etc.

**Snack**:
- `cinemaId` must exist
- `name` required, max 255 characters
- `price` must be >= 0, decimal format
- `type` can be: FOOD, BEVERAGE, COMBO, CANDY, etc.
- `imageUrl` should be valid URL format

### Error Response Format

All errors follow the same envelope:
```json
{
  "code": 400,
  "message": "Error description",
  "data": {
    "field_name": ["Error message 1", "Error message 2"]
  }
}
```

Or for simple errors:
```json
{
  "code": 404,
  "message": "Resource not found",
  "data": null
}
```

---

## Testing with cURL

### List all users
```bash
curl -X GET http://localhost:8000/api/users \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json"
```

### Get user by ID
```bash
curl -X GET http://localhost:8000/api/users/3fa85f64-5717-4562-b3fc-2c963f66afa6 \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json"
```

### Update user role
```bash
curl -X PATCH http://localhost:8000/api/users/3fa85f64-5717-4562-b3fc-2c963f66afa6/role \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"role":"ADMIN"}'
```

### Delete user
```bash
curl -X DELETE http://localhost:8000/api/users/3fa85f64-5717-4562-b3fc-2c963f66afa6 \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json"
```

### Add room to cinema
```bash
curl -X POST http://localhost:8000/api/cinemas/rooms \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "cinemaId":"cinema-1111-aaaa-2222",
    "roomType":"IMAX",
    "roomNumber":1
  }'
```

### Add snack to cinema
```bash
curl -X POST http://localhost:8000/api/cinemas/snacks \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "cinemaId":"3e4a8c9f-1234-5678-90ab-cdef12345678",
    "name":"Popcorn Combo",
    "description":"Large popcorn + 2 drinks",
    "price":120000.00,
    "type":"COMBO",
    "imageUrl":"https://cdn.example.com/popcorn-combo.jpg",
    "imageCloudinaryId":"snacks/popcorn_combo_abc123"
  }'
```

---

**Implementation Status**: ✅ Complete  
**Last Updated**: December 16, 2025  
**Framework**: Laravel 12 | PHP 8.3.16
