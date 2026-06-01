# Backend Setup Guide

This document provides instructions for setting up and running the Node.js/Express backend with Supabase integration.

## Quick Start

### 1. Install Dependencies
```bash
cd backend
npm install
```

### 2. Configure Environment Variables
The `.env` file has been created with your Supabase credentials. No additional configuration needed for local development.

### 3. Set Up Database Schema

Your Supabase database needs the schema created. Do one of the following:

**Option A: Using Supabase Dashboard**
1. Go to https://app.supabase.com
2. Select your project (hzmvhykjkhgxuclfscye)
3. Navigate to SQL Editor
4. Click "New Query"
5. Copy the entire contents of `backend/src/db/schema.sql`
6. Paste it into the query editor
7. Click "Run"

**Option B: Using psql CLI**
```bash
psql postgres://postgres.hzmvhykjkhgxuclfscye:R9lX58ZBhm8SMFwC@aws-1-us-east-1.pooler.supabase.com:5432/postgres?sslmode=require < backend/src/db/schema.sql
```

### 4. Start the Backend Server

**Development mode:**
```bash
npm run dev
```

Server will be available at `http://localhost:3001`

**Production build:**
```bash
npm run build
npm start
```

## API Documentation

### Health Check
```bash
curl http://localhost:3001/health
```

### Authentication

**Register:**
```bash
curl -X POST http://localhost:3001/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{"email": "user@example.com", "password": "password123"}'
```

**Login:**
```bash
curl -X POST http://localhost:3001/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email": "user@example.com", "password": "password123"}'
```

Response includes:
```json
{
  "user": { "id": "...", "email": "..." },
  "token": "eyJhbGci..."
}
```

Use the token in subsequent requests:
```bash
curl http://localhost:3001/api/patients \
  -H "Authorization: Bearer <token>"
```

### Patients Endpoints

**List all patients:**
```bash
GET /api/patients
```

**Get patient by ID:**
```bash
GET /api/patients/:id
```

**Create patient:**
```bash
POST /api/patients
Content-Type: application/json

{
  "fullName": "John Doe",
  "gender": "male",
  "patientType": "outdoor",
  "allergies": [],
  "chronicConditions": []
}
```

**Update patient:**
```bash
PUT /api/patients/:id
```

**Delete patient:**
```bash
DELETE /api/patients/:id
```

**Get patients modified since timestamp:**
```bash
GET /api/patients/sync/since/:timestamp
```

### Visits Endpoints

**List all visits:**
```bash
GET /api/visits
```

**Get visits for a patient:**
```bash
GET /api/visits/patient/:patientId
```

**Create visit:**
```bash
POST /api/visits
Content-Type: application/json

{
  "patientId": "uuid",
  "visitDate": "2026-06-01T10:00:00Z",
  "chiefComplaint": "Fever",
  "visitType": "outdoor",
  "vitalSigns": {
    "temperature": "38.5",
    "pulse": "80"
  }
}
```

### Prescriptions Endpoints

**List all prescriptions:**
```bash
GET /api/prescriptions
```

**Get prescriptions for patient:**
```bash
GET /api/prescriptions/patient/:patientId
```

**Get prescriptions for visit:**
```bash
GET /api/prescriptions/visit/:visitId
```

**Create prescription:**
```bash
POST /api/prescriptions
Content-Type: application/json

{
  "patientId": "uuid",
  "prescriptionDate": "2026-06-01T10:00:00Z",
  "medications": [
    {
      "name": "Aspirin",
      "dose": "500mg",
      "frequency": "Twice daily",
      "duration": "7 days",
      "instructions": "After meals"
    }
  ]
}
```

## Troubleshooting

### Connection Issues

If you get "Connection refused" errors:
1. Verify Supabase is running: https://app.supabase.com
2. Check your `.env` file has correct credentials
3. Ensure firewall allows outbound connections to Supabase

### Authentication Errors

If you get "Unauthorized" responses:
1. Ensure you're including the `Authorization: Bearer <token>` header
2. Verify the token hasn't expired (24-hour expiry)
3. Login again to get a fresh token

### Database Errors

If you get "relation does not exist" errors:
1. Run the schema.sql file to create tables
2. Verify all tables were created: `SELECT * FROM information_schema.tables WHERE table_schema='public';`

## Development Workflow

1. **Start the backend:**
   ```bash
   cd backend
   npm run dev
   ```

2. **In another terminal, start the frontend:**
   ```bash
   cd src/frontend
   npm run dev
   ```

3. **Frontend will be available at:** http://localhost:5173
4. **Backend API at:** http://localhost:3001
5. **API requests will be proxied** via the Vite dev server

## Environment Variables Reference

| Variable | Description | Example |
|----------|-------------|----------|
| `SUPABASE_URL` | Supabase project URL | https://hzmvhykjkhgxuclfscye.supabase.co |
| `SUPABASE_ANON_KEY` | Public key for Supabase Auth | eyJhbGci... |
| `SUPABASE_SERVICE_KEY` | Server key for admin operations | eyJhbGci... |
| `PORT` | Server port | 3001 |
| `NODE_ENV` | Environment | development/production |
| `JWT_SECRET` | Secret for JWT signing | VLdOCGikI9Wi/iVX1... |
| `CLIENT_URL` | Frontend URL for CORS | http://localhost:5173 |
| `LOG_LEVEL` | Winston log level | info/debug/error |

## Next Steps

1. ✅ Backend is set up and running
2. ⏭️ Update frontend to use new API endpoints
3. ⏭️ Run database migrations for data from Motoko backend
4. ⏭️ Test all endpoints
5. ⏭️ Deploy to production
