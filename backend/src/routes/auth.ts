import { Router } from 'express';
import { supabase } from '../db/supabase';
import jwt from 'jsonwebtoken';
import { AppError } from '../middleware/errorHandler';

const router = Router();
const JWT_SECRET = process.env.JWT_SECRET || 'your-secret-key';

// Register
router.post('/register', async (req, res) => {
  const { email, password, role = 'user' } = req.body;

  if (!email || !password) {
    throw new AppError(400, 'Email and password required');
  }

  const { data, error } = await supabase.auth.signUpWithPassword({
    email,
    password,
  });

  if (error) {
    throw new AppError(400, error.message);
  }

  // Create user record in users table
  await supabase.from('users').insert([
    {
      id: data.user?.id,
      email,
      role,
    },
  ]);

  res.status(201).json({ user: data.user });
});

// Login
router.post('/login', async (req, res) => {
  const { email, password } = req.body;

  if (!email || !password) {
    throw new AppError(400, 'Email and password required');
  }

  const { data, error } = await supabase.auth.signInWithPassword({
    email,
    password,
  });

  if (error) {
    throw new AppError(401, 'Invalid credentials');
  }

  // Get user role
  const { data: userData } = await supabase
    .from('users')
    .select('role')
    .eq('id', data.user?.id)
    .single();

  // Create JWT token
  const token = jwt.sign(
    {
      id: data.user?.id,
      email: data.user?.email,
      role: userData?.role || 'user',
    },
    JWT_SECRET,
    { expiresIn: '24h' }
  );

  res.json({
    user: data.user,
    token,
  });
});

// Logout
router.post('/logout', async (req, res) => {
  await supabase.auth.signOut();
  res.json({ message: 'Logged out successfully' });
});

export default router;
