# InsuriVault Client API Documentation for AI

This document provides a technical overview of the InsuriVault Client API, designed to be easily parsed and used by AI agents.

## Base URL
The API is typically hosted at `https://client-api.insuri-vault.com` or `http://localhost:5001` in development.

## Authentication
Most endpoints require a JWT token in the `Authorization` header.

**Header Format:**
```http
Authorization: Bearer {your_jwt_token}
```

---

## Endpoints

### 1. User Authentication

#### `POST /UserAuthentication/GetToken`
Generates a JWT authentication token for a client.

**Request Body:**
- `Email` (string, required): The user's email address.
- `Password` (string, required): The user's password.
- `OriginHost` (string, optional): The hostname of the requesting application (e.g., `client-portal.example.com`). This is used to resolve the correct database context.

**Example Request:**
```json
{
  "email": "user@example.com",
  "password": "SecurePassword123",
  "originHost": "client-portal.insuri-vault.com"
}
```

**Example Response (200 OK):**
```json
{
  "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9..."
}
```

---

### 2. Account File Storage

#### `POST /AccountFileStorage/List`
Lists files available to the authenticated client, with optional filtering. Requires Authentication.

**Request Body (Optional):**
- `accountId` (int, optional): Filter by a specific account ID.
- `fileCategory` (string, optional): Filter by category (e.g., "Statement", "Policy").
- `year` (int, optional): Filter by year.
- `month` (int, optional): Filter by month (1-12).

**Example Request (All Files):**
```json
{}
```

**Example Request (Filtered):**
```json
{
  "accountId": 101,
  "fileCategory": "Statement",
  "year": 2025,
  "month": 1
}
```

**Example Response (200 OK):**
```json
[
  {
    "account": {
      "id": 101,
      "name": "John Doe",
      "email": "john.doe@example.com"
    },
    "files": [
      {
        "fileId": "3fa85f64-5717-4562-b3fc-2c963f66afa6",
        "fileCategory": "Statement",
        "year": 2025,
        "month": 1,
        "originalFileName": "statement_jan_2025.pdf",
        "contentType": "application/pdf",
        "uploadedAtUtc": "2025-01-15T10:00:00Z",
        "eTag": "\"a1b2c3d4\""
      }
    ]
  }
]
```

#### `POST /AccountFileStorage/Download`
Downloads a specific file. Requires Authentication.

**Request Body:**
- `accountId` (int, required): The ID of the account the file belongs to.
- `fileId` (guid, required): The unique identifier of the file.
- `downloadFormat` (string/int, optional): Format of the response.
    - `0` or `EncodedString` (Default): Returns the file content as a Base64 encoded string in the JSON response.
    - `1` or `BinaryFile`: Returns the file as a binary stream (octet-stream).

**Example Request (Base64):**
```json
{
  "accountId": 101,
  "fileId": "3fa85f64-5717-4562-b3fc-2c963f66afa6",
  "downloadFormat": "EncodedString"
}
```

**Example Response (200 OK, EncodedString):**
```json
"JVBERi0xLjQKJ... (Base64 string content)"
```

**Example Response (200 OK, BinaryFile):**
Returns a binary file stream with `Content-Type: application/octet-stream` and `Content-Disposition: attachment; filename="..."`.

---

## Data Models

### AccountFileInfo
| Property | Type | Description |
| :--- | :--- | :--- |
| `fileId` | Guid | Unique identifier for the file. |
| `fileCategory` | String | Category of the file. |
| `year` | Int? | Year associated with the file. |
| `month` | Int? | Month associated with the file. |
| `originalFileName`| String | The original name of the file when uploaded. |
| `contentType` | String | MIME type of the file. |
| `uploadedAtUtc` | DateTime| Timestamp of upload in UTC. |
| `eTag` | String? | Entity tag for caching/concurrency. |

---

## Guidelines for AI Usage
- **Context Resolution:** When calling `GetToken`, ensure `originHost` matches the expected client domain to ensure the correct database is accessed.
- **Error Handling:** Handle `401 Unauthorized` by re-authenticating and `404 Not Found` when a file or account is missing.
- **File Downloads:** For small files or browser-based integrations, `EncodedString` is often easier to handle. For large files or direct saving, use `BinaryFile`.
