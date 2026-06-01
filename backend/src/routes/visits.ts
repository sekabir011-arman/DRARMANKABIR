import { Router } from 'express';
import { supabase } from '../db/supabase';
import { authMiddleware } from '../middleware/auth';
import { AppError } from '../middleware/errorHandler';
import { v4 as uuid } from 'uuid';

const router = Router();

// Create visit
router.post('/', authMiddleware, async (req, res) => {
  const visitData = {
    id: uuid(),
    patient_id: req.body.patientId,
    visit_date: req.body.visitDate,
    chief_complaint: req.body.chiefComplaint,
    history_of_present_illness: req.body.historyOfPresentIllness,
    vital_signs: req.body.vitalSigns,
    physical_examination: req.body.physicalExamination,
    diagnosis: req.body.diagnosis,
    notes: req.body.notes,
    visit_type: req.body.visitType,
  };

  const { data, error } = await supabase
    .from('visits')
    .insert([visitData])
    .select();

  if (error) {
    throw new AppError(400, error.message);
  }

  res.status(201).json(data?.[0]);
});

// Get all visits
router.get('/', authMiddleware, async (req, res) => {
  const { data, error } = await supabase
    .from('visits')
    .select('*')
    .order('visit_date', { ascending: false });

  if (error) {
    throw new AppError(400, error.message);
  }

  res.json(data);
});

// Get visit by ID
router.get('/:id', authMiddleware, async (req, res) => {
  const { data, error } = await supabase
    .from('visits')
    .select('*')
    .eq('id', req.params.id)
    .single();

  if (error || !data) {
    throw new AppError(404, 'Visit not found');
  }

  res.json(data);
});

// Get visits by patient ID
router.get('/patient/:patientId', authMiddleware, async (req, res) => {
  const { data, error } = await supabase
    .from('visits')
    .select('*')
    .eq('patient_id', req.params.patientId)
    .order('visit_date', { ascending: false });

  if (error) {
    throw new AppError(400, error.message);
  }

  res.json(data);
});

// Update visit
router.put('/:id', authMiddleware, async (req, res) => {
  const { data, error } = await supabase
    .from('visits')
    .update({
      patient_id: req.body.patientId,
      visit_date: req.body.visitDate,
      chief_complaint: req.body.chiefComplaint,
      history_of_present_illness: req.body.historyOfPresentIllness,
      vital_signs: req.body.vitalSigns,
      physical_examination: req.body.physicalExamination,
      diagnosis: req.body.diagnosis,
      notes: req.body.notes,
      visit_type: req.body.visitType,
      updated_at: new Date().toISOString(),
    })
    .eq('id', req.params.id)
    .select()
    .single();

  if (error || !data) {
    throw new AppError(400, error?.message || 'Failed to update visit');
  }

  res.json(data);
});

// Delete visit
router.delete('/:id', authMiddleware, async (req, res) => {
  const { error } = await supabase
    .from('visits')
    .delete()
    .eq('id', req.params.id);

  if (error) {
    throw new AppError(400, error.message);
  }

  res.json({ message: 'Visit deleted' });
});

export default router;
