# InsuriVault Client API Reference

## Overview

The InsuriVault Client API is a .NET 9 ASP.NET Core REST API that provides client-facing authentication and file storage capabilities. It uses a multi-tenant architecture where each organization has its own database, resolved at runtime via IP address and organization name.

Base URL pattern: `https://client-api-dev.insuri-vault.com`

All responses use standard HTTP status codes. Errors return plain text or JSON messages.

---

## Authentication

The API uses **JWT Bearer tokens**. Include the token in the `Authorization` header:

```
Authorization: Bearer <token>
```

**Token details:**
- Issuer & Audience: `InsuriVault.ClientApi`
- Algorithm: HMAC-SHA256
- Default TTL: 60 minutes

**JWT Claims:**

| Claim | Description |
|-------|-------------|
| `sub` (Name) | Username (if provided) |
| `jti` | Unique token ID |
| `role` | Client role (see ClientRole enum) |
| `email` | Client email address |
| `Organization` | Organization name (used for tenant resolution) |
| `AccountId` | Numeric account ID |
| `ClientId` | Numeric client ID |
| `DatabaseName` | Tenant database context |

---

## Multi-Tenant Resolution

Every request resolves the client's tenant database through this flow:

1. The caller provides an `Organization` name (directly or via `originHost` lookup during login).
2. For token-based requests, the API extracts the `Organization` claim from the JWT.
3. The API verifies that the organization is active and not disabled in the master database.
4. The tenant database name is resolved and subsequent service calls operate against that database.

If the organization is not found, inactive, or disabled, the request is rejected with `401 Unauthorized`.

---

## Enums

### ClientRole
| Value | Name |
|-------|------|
| 0 | None |
| 1 | TestRole |
| 2 | SuperUser |
| 3 | Admin |
| 4 | Owner |
| 5 | User |

### FileCategory
| Value | Name |
|-------|------|
| 0 | Unknown |
| 1 | Insurance |
| 2 | TaxForms |

### DownloadFormat
| Value | Name | Behavior |
|-------|------|----------|
| 0 | EncodedString | Returns file content as a Base64-encoded JSON string |
| 1 | BinaryFile | Returns raw binary stream with `Content-Disposition: attachment` |

---

## Endpoints

### 1. POST `/UserAuthentication/GetToken`

Authenticates a client with email and password, returns a JWT token.

**Auth required:** No

**Request body:**
```json
{
  "email": "user@example.com",
  "password": "secretPassword",
  "organization": "AcmeCorp",
  "originHost": "https://app.acmecorp.com"
}
```

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `email` | string | Yes | Client email address |
| `password` | string | Yes | Client password |
| `organization` | string | No* | Organization name for tenant resolution |
| `originHost` | string | No* | Hostname of the requesting application; used to look up the organization if `organization` is not provided |

*At least one of `organization` or `originHost` must be provided.

**Success response (200):**
```json
{
  "token": "eyJhbGciOiJIUzI1NiIs..."
}
```

**Error responses:**
- `400` — Missing email/password, or missing organization/originHost
- `401` — IP not whitelisted, user not found, user disabled, or wrong password
- `500` — Internal server error

**Flow:**
1. Validates email and password are present
2. Resolves organization (from `organization` field, or by looking up `originHost` in master database)
3. Resolves tenant database using caller IP + organization (IP verification happens at login only)
4. Looks up client by email in tenant database
5. Verifies client is active and not disabled
6. Verifies client has at least one account access record
7. Verifies password hash
8. Generates and returns JWT token (containing the `Organization` claim)

---

### 2. POST `/BiometricAuthentication/RegisterOptions`

Initiates WebAuthn/FIDO2 biometric credential registration. Returns challenge options that the client device uses to create a credential.

**Auth required:** Yes (JWT)

**Request body:**
```json
"https://app.acmecorp.com"
```
A plain JSON string representing the origin host. Optional (can be `null`).

**Success response (200):** Returns a FIDO2 `CredentialCreateOptions` object containing the challenge, relying party info, user info, and supported algorithms. Pass this to the WebAuthn browser API (`navigator.credentials.create()`).

**Error responses:**
- `401` — Not authenticated or email not in token
- `400` — Invalid host
- `404` — User not found

---

### 3. POST `/BiometricAuthentication/CompleteRegistration`

Completes biometric credential registration after the client device has created a credential.

**Auth required:** Yes (JWT)

**Request body:** A FIDO2 `AuthenticatorAttestationRawResponse` object (the result from `navigator.credentials.create()`).

**Query parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `challenge` | string | Yes | The challenge string from the `RegisterOptions` response |
| `originHost` | string | No | Origin host for tenant resolution |

**Success response (200):** Returns the FIDO2 registration result with credential ID and public key info.

**Error responses:**
- `400` — Invalid challenge or registration verification failed
- `401` — Not authenticated

---

### 4. POST `/BiometricAuthentication/AssertionOptions`

Initiates biometric login. Returns a challenge for the client device to sign.

**Auth required:** No

**Request body:**
```json
"user@example.com"
```
A plain JSON string with the client's email address.

**Query parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `originHost` | string | No | Origin host for tenant resolution |

**Success response (200):** Returns a FIDO2 `AssertionOptions` object. Pass this to the WebAuthn browser API (`navigator.credentials.get()`).

**Error responses:**
- `400` — Invalid host
- `404` — User not found

---

### 5. POST `/BiometricAuthentication/CompleteAssertion`

Completes biometric login and returns a JWT token.

**Auth required:** No

**Request body:** A FIDO2 `AuthenticatorAssertionRawResponse` object (the result from `navigator.credentials.get()`).

**Query parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `challenge` | string | Yes | The challenge string from the `AssertionOptions` response |
| `email` | string | Yes | Client email address |
| `originHost` | string | No | Origin host for tenant resolution |

**Success response (200):**
```json
{
  "token": "eyJhbGciOiJIUzI1NiIs..."
}
```

**Error responses:**
- `400` — Invalid challenge
- `401` — User not found, credential mismatch, or assertion verification failed

---

### 6. DELETE `/BiometricAuthentication/DeleteCredential`

Removes a registered biometric credential.

**Auth required:** Yes (JWT)

**Query parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `credentialId` | string | Yes | Base64URL-encoded credential ID to delete |
| `originHost` | string | No | Origin host for tenant resolution |

**Success response (200):** Empty body.

**Error responses:**
- `400` — Invalid host or deletion failed
- `401` — Not authenticated
- `404` — User not found

---

### 7. POST `/AccountFileStorage/List`

Lists files the authenticated client has access to, with optional filters.

**Auth required:** Yes (JWT)

**Request body:**
```json
{
  "accountId": 101,
  "fileCategory": 1,
  "year": 2025,
  "month": 1
}
```

All fields are optional. If `accountId` is omitted, files for **all** accounts the client has access to are returned.

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `accountId` | int | No | Filter to a specific account |
| `fileCategory` | int (FileCategory) | No | Filter by file category (0=Unknown, 1=Insurance, 2=TaxForms) |
| `year` | int | No | Filter by year (UTC) |
| `month` | int | No | Filter by month 1-12 (UTC) |

**Success response (200):**
```json
[
  {
    "account": {
      "id": 101,
      "name": "John Doe",
      "isActive": true,
      "isDisabled": false
    },
    "files": [
      {
        "fileId": "3fa85f64-5717-4562-b3fc-2c963f66afa6",
        "fileCategory": "Insurance",
        "year": 2025,
        "month": 1,
        "originalFileName": "policy_jan_2025.pdf",
        "contentType": "application/pdf",
        "uploadedAtUtc": "2025-01-15T10:00:00+00:00",
        "eTag": "\"a1b2c3d4\""
      }
    ]
  }
]
```

**Error responses:**
- `401` — Invalid token, missing ClientId or Organization claim, or inactive/disabled organization account
- `404` — No files found

---

### 8. POST `/AccountFileStorage/Download`

Downloads a specific file.

**Auth required:** Yes (JWT)

**Request body:**
```json
{
  "accountId": 101,
  "fileId": "3fa85f64-5717-4562-b3fc-2c963f66afa6",
  "downloadFormat": 0
}
```

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `accountId` | int | Yes | Account that owns the file |
| `fileId` | string (GUID) | Yes | File identifier from the List response |
| `downloadFormat` | int (DownloadFormat) | No | 0 = Base64 JSON string (default), 1 = binary file stream |

**Success response (200):**
- **EncodedString (0):** Returns a Base64-encoded string in JSON: `"JVBERi0xLjQK..."`
- **BinaryFile (1):** Returns raw binary with `Content-Type` matching the file and `Content-Disposition: attachment; filename="original_name.pdf"`

**Error responses:**
- `400` — Invalid AccountId or FileId
- `401` — Invalid token, missing ClientId or Organization claim, or inactive/disabled organization account
- `404` — File not found

---

## Typical Request Flow

```
Step 1: Authenticate
    POST /UserAuthentication/GetToken
    Body: { "email": "...", "password": "...", "organization": "..." }
    Response: { "token": "eyJ..." }

Step 2 (optional): Register biometric credential
    POST /BiometricAuthentication/RegisterOptions         [Bearer token]
    → pass result to navigator.credentials.create()
    POST /BiometricAuthentication/CompleteRegistration     [Bearer token]

Step 3 (alternative login): Biometric login
    POST /BiometricAuthentication/AssertionOptions
    Body: "user@example.com"
    → pass result to navigator.credentials.get()
    POST /BiometricAuthentication/CompleteAssertion
    Response: { "token": "eyJ..." }

Step 4: List files
    POST /AccountFileStorage/List                          [Bearer token]
    Body: {} or { "accountId": 101, "year": 2025 }

Step 5: Download a file
    POST /AccountFileStorage/Download                      [Bearer token]
    Body: { "accountId": 101, "fileId": "...", "downloadFormat": 0 }
```

---

## Key Architecture Notes

- **Multi-tenant:** Each organization has a dedicated database. The master database maps organization names to tenant database names.
- **Organization-based access:** Tokens now include an `Organization` claim, which is validated on every request to ensure the organization remains active and not disabled.
- **File storage backend:** Azure Blob Storage. Containers are named `master-{masterAccountId}`.
- **Access control:** Clients can only access files for accounts they have explicit `ClientAccountAccess` records for.
- **WebAuthn domain:** `client-api-dev.insuri-vault.com` (configured in the FIDO2 server settings).
- **Challenges are cached in-memory:** Biometric registration and assertion challenges are stored in static dictionaries on the server. They are consumed (removed) after use.
