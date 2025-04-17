# Authentication Microservice

This is a simple authentication microservice with role-based access control (RBAC).

## Roles
The system supports three roles:
- `administrator` - Has full access to the system
- `analyst` - Has limited access to the system
- `guest` - Has minimal access to the system

## Database Setup

Run the SQL script in `sql/init.sql` to set up the database.

Default credentials:
- Username: `admin`
- Password: `admin123`

## API Endpoints

### Authentication

#### Login
- **URL**: `/api/login.php`
- **Method**: `POST`
- **Auth Required**: No
- **Body**:
```json
{
  "username": "admin",
  "password": "admin123"
}
```
- **Success Response**: 
```json
{
  "status": "success",
  "message": "Success",
  "data": {
    "token": "JWT_TOKEN",
    "user": {
      "id": 1,
      "username": "admin",
      "email": "admin@example.com",
      "role": "administrator"
    }
  }
}
```

#### Validate Token
- **URL**: `/api/validate-token.php`
- **Method**: `POST`
- **Auth Required**: Yes (Bearer Token)
- **Success Response**: 
```json
{
  "status": "success",
  "message": "Success",
  "data": {
    "user": {
      "id": 1,
      "username": "admin",
      "email": "admin@example.com",
      "role": "administrator"
    }
  }
}
```

#### Refresh Token
- **URL**: `/api/refresh-token.php`
- **Method**: `POST`
- **Auth Required**: Yes (Bearer Token)
- **Success Response**: 
```json
{
  "status": "success",
  "message": "Success",
  "data": {
    "token": "NEW_JWT_TOKEN",
    "user": {
      "id": 1,
      "username": "admin",
      "email": "admin@example.com",
      "role": "administrator"
    }
  }
}
```

### Users

#### Get All Users
- **URL**: `/api/users/index.php`
- **Method**: `GET`
- **Auth Required**: Yes (Bearer Token with administrator role)
- **Success Response**: 
```json
{
  "status": "success",
  "message": "Success",
  "data": [
    {
      "id": 1,
      "username": "admin",
      "email": "admin@example.com",
      "active": 1,
      "role": "administrator"
    }
  ]
}
```

#### Create User
- **URL**: `/api/users/index.php`
- **Method**: `POST`
- **Auth Required**: Yes (Bearer Token with administrator role)
- **Body**:
```json
{
  "username": "newuser",
  "password": "password123",
  "email": "newuser@example.com",
  "role_id": 1
}
```
- **Success Response**: 
```json
{
  "status": "success",
  "message": "User created successfully",
  "data": {
    "id": 2
  }
}
```

#### Get User by ID
- **URL**: `/api/users/user.php?id=1`
- **Method**: `GET`
- **Auth Required**: Yes (Bearer Token with administrator role or self)
- **Success Response**: 
```json
{
  "status": "success",
  "message": "Success",
  "data": {
    "id": 1,
    "username": "admin",
    "email": "admin@example.com",
    "active": 1,
    "role": "administrator"
  }
}
```

#### Update User
- **URL**: `/api/users/user.php?id=1`
- **Method**: `PUT`
- **Auth Required**: Yes (Bearer Token with administrator role or self)
- **Body**:
```json
{
  "username": "admin",
  "email": "newemail@example.com",
  "password": "newpassword123",
  "role_id": 1,
  "active": true
}
```
- **Success Response**: 
```json
{
  "status": "success",
  "message": "User updated successfully",
  "data": {
    "id": 1,
    "username": "admin",
    "email": "newemail@example.com",
    "active": 1,
    "role": "administrator"
  }
}
```

#### Delete User
- **URL**: `/api/users/user.php?id=2`
- **Method**: `DELETE`
- **Auth Required**: Yes (Bearer Token with administrator role)
- **Success Response**: 
```json
{
  "status": "success",
  "message": "User deleted successfully",
  "data": null
}
```

### Roles

#### Get All Roles
- **URL**: `/api/roles/index.php`
- **Method**: `GET`
- **Auth Required**: Yes (Bearer Token)
- **Success Response**: 
```json
{
  "status": "success",
  "message": "Success",
  "data": [
    {
      "id": 1,
      "name": "administrator",
      "created_at": "2023-07-01 12:00:00",
      "updated_at": "2023-07-01 12:00:00"
    },
    {
      "id": 2,
      "name": "analyst",
      "created_at": "2023-07-01 12:00:00",
      "updated_at": "2023-07-01 12:00:00"
    },
    {
      "id": 3,
      "name": "guest",
      "created_at": "2023-07-01 12:00:00",
      "updated_at": "2023-07-01 12:00:00"
    }
  ]
}
```

#### Create Role
- **URL**: `/api/roles/index.php`
- **Method**: `POST`
- **Auth Required**: Yes (Bearer Token with administrator role)
- **Body**:
```json
{
  "name": "new_role"
}
```
- **Success Response**: 
```json
{
  "status": "success",
  "message": "Role created successfully",
  "data": {
    "id": 4,
    "name": "new_role",
    "created_at": "2023-07-01 12:00:00",
    "updated_at": "2023-07-01 12:00:00"
  }
}
```

#### Get Role by ID
- **URL**: `/api/roles/role.php?id=1`
- **Method**: `GET`
- **Auth Required**: Yes (Bearer Token)
- **Success Response**: 
```json
{
  "status": "success",
  "message": "Success",
  "data": {
    "id": 1,
    "name": "administrator",
    "created_at": "2023-07-01 12:00:00",
    "updated_at": "2023-07-01 12:00:00"
  }
}
```

#### Update Role
- **URL**: `/api/roles/role.php?id=1`
- **Method**: `PUT`
- **Auth Required**: Yes (Bearer Token with administrator role)
- **Body**:
```json
{
  "name": "admin"
}
```
- **Success Response**: 
```json
{
  "status": "success",
  "message": "Role updated successfully",
  "data": {
    "id": 1,
    "name": "admin",
    "created_at": "2023-07-01 12:00:00",
    "updated_at": "2023-07-01 12:00:00"
  }
}
```

#### Delete Role
- **URL**: `/api/roles/role.php?id=4`
- **Method**: `DELETE`
- **Auth Required**: Yes (Bearer Token with administrator role)
- **Success Response**: 
```json
{
  "status": "success",
  "message": "Role deleted successfully",
  "data": null
}
```

## JWT Integration

To integrate with other microservices, include the JWT token in the Authorization header:

```
Authorization: Bearer YOUR_JWT_TOKEN
```

Then verify the token using the `/api/validate-token.php` endpoint from other microservices.

## Database Configuration

The database connection is configured in `config/database.php`:

```php
define('AUTH_DB_HOST', 'localhost');
define('AUTH_DB_NAME', 'admin_contragent_auth');
define('AUTH_DB_USER', 'admin_contragent_auth');
define('AUTH_DB_PASSWORD', 'Lalka112233');
``` 