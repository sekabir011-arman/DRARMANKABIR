import { Router } from 'express';
import { supabase } from '../db/supabase';
import { authMiddleware, AuthRequest } from '../middleware/auth';
import { AppError } from '../middleware/errorHandler';
import { v4 as uuid } from 'uuid';

const router = Router();

// Get current user profile
router.get('/me', authMiddleware, async (req: AuthRequest, res) => {
  const userId = req.user?.id;

  if (!userId) {
    throw new AppError(401, 'Unauthorized');
  }

  const { data, error } = await supabase
    .from('user_profiles')
    .select('*')
    .eq('user_id', userId)
    .single();

  if (error || !data) {
    throw new AppError(404, 'User profile not found');
  }

  res.json(data);
});

// Get user profile by ID
router.get('/:userId', authMiddleware, async (req: AuthRequest, res) => {
  const { data, error } = await supabase
    .from('user_profiles')
    .select('*')
    .eq('user_id', req.params.userId)
    .single();

  if (error || !data) {
    throw new AppError(404, 'User profile not found');
  }

  res.json(data);
});

// Create or update user profile
router.post('/me/save', authMiddleware, async (req: AuthRequest, res) => {
  const userId = req.user?.id;
  const { name } = req.body;

  if (!userId || !name) {
    throw new AppError(400, 'User ID and name required');
  }

  // Try to update, if not found, insert
  const { data: existingProfile } = await supabase
    .from('user_profiles')
    .select('*')
    .eq('user_id', userId)
    .single();

  let result;
  if (existingProfile) {
    result = await supabase
      .from('user_profiles')
      .update({
        name,
        updated_at: new Date().toISOString(),
      })
      .eq('user_id', userId)
      .select()
      .single();
  } else {
    result = await supabase
      .from('user_profiles')
      .insert([{
        id: uuid(),
        user_id: userId,
        name,
      }])
      .select()
      .single();
  }

  const { data, error } = result;

  if (error) {
    throw new AppError(400, error.message);
  }

  res.json(data);
});

export default router;
