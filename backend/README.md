# Dr. Arman Kabir's Care - Backend

Node.js/Express backend with Supabase PostgreSQL integration for patient management system.

## Setup

### Prerequisites
- Node.js 18+
- Supabase account and project

### Installation

1. Clone the repository and navigate to the backend directory:
```bash
cd backend
npm install
```

2. Create a `.env` file from `.env.example`:
```bash
cp .env.example .env
```

3. Fill in your Supabase credentials in `.env`:
- `SUPABASE_URL`: Your Supabase project URL
- `SUPABASE_SERVICE_KEY`: Your Supabase service role key
- `JWT_SECRET`: A secret key for JWT tokens
- `CLIENT_URL`: Frontend URL (for CORS)

### Database Setup

1. Go to your Supabase project dashboard
2. Navigate to SQL Editor
3. Create a new query and paste the contents of `src/db/schema.sql`
4. Execute the query to create all tables and indexes

### Running the Server

**Development:**
```bash
npm run dev
```

**Production Build:**
```bash
npm run build
npm start
```

## API Endpoints

### Authentication
- `POST /api/auth/register` - Register new user
- `POST /api/auth/login` - Login user
- `POST /api/auth/logout` - Logout user

### Patients
- `GET /api/patients` - List all patients
- `GET /api/patients/:id` - Get patient by ID
- `POST /api/patients` - Create new patient
- `PUT /api/patients/:id` - Update patient
- `DELETE /api/patients/:id` - Delete patient
- `GET /api/patients/sync/since/:timestamp` - Get patients modified since timestamp

### Visits
- `GET /api/visits` - List all visits
- `GET /api/visits/:id` - Get visit by ID
- `GET /api/visits/patient/:patientId` - Get visits for a patient
- `POST /api/visits` - Create new visit
- `PUT /api/visits/:id` - Update visit
- `DELETE /api/visits/:id` - Delete visit

### Prescriptions
- `GET /api/prescriptions` - List all prescriptions
- `GET /api/prescriptions/:id` - Get prescription by ID
- `GET /api/prescriptions/patient/:patientId` - Get prescriptions for a patient
- `GET /api/prescriptions/visit/:visitId` - Get prescriptions for a visit
- `POST /api/prescriptions` - Create new prescription
- `PUT /api/prescriptions/:id` - Update prescription
- `DELETE /api/prescriptions/:id` - Delete prescription

### User Profiles
- `GET /api/user-profiles/me` - Get current user profile
- `GET /api/user-profiles/:userId` - Get user profile
- `POST /api/user-profiles/me/save` - Save/update user profile

## Authentication

All protected endpoints require a JWT token in the Authorization header:
```
Authorization: Bearer <token>
```

Tokens are obtained from the login endpoint and expire after 24 hours.

## Environment Variables

- `SUPABASE_URL` - Your Supabase project URL
- `SUPABASE_SERVICE_KEY` - Supabase service role key (use for server-side operations)
- `PORT` - Server port (default: 3001)
- `NODE_ENV` - Environment (development/production)
- `JWT_SECRET` - Secret for JWT signing
- `CLIENT_URL` - Frontend URL for CORS
- `LOG_LEVEL` - Winston logger level (default: info)

## Project Structure

```
src/
├── index.ts           # Entry point
├── types/             # TypeScript type definitions
├── middleware/        # Express middleware
├── routes/            # API route handlers
├── db/                # Database configuration and schema
└── utils/             # Utility functions
```

## Error Handling

All errors are caught and formatted as JSON responses:
```json
{
  "error": "Error message"
}
```

## Logging

Winston logger is configured to:
- Log to console in development
- Log to files in all environments
- Error logs: `error.log`
- Combined logs: `combined.log`

## License

MIT
