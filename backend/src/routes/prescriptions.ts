import { Router } from 'express';
import { supabase } from '../db/supabase';
import { authMiddleware } from '../middleware/auth';
import { AppError } from '../middleware/errorHandler';
import { v4 as uuid } from 'uuid';

const router = Router();

// Create prescription
router.post('/', authMiddleware, async (req, res) => {
  const prescriptionData = {
    id: uuid(),
    patient_id: req.body.patientId,
    visit_id: req.body.visitId,
    prescription_date: req.body.prescriptionDate,
    diagnosis: req.body.diagnosis,
    medications: req.body.medications,
    notes: req.body.notes,
  };

  const { data, error } = await supabase
    .from('prescriptions')
    .insert([prescriptionData])
    .select();

  if (error) {
    throw new AppError(400, error.message);
  }

  res.status(201).json(data?.[0]);
});

// Get all prescriptions
router.get('/', authMiddleware, async (req, res) => {
  const { data, error } = await supabase
    .from('prescriptions')
    .select('*')
    .order('prescription_date', { ascending: false });

  if (error) {
    throw new AppError(400, error.message);
  }

  res.json(data);
});

// Get prescription by ID
router.get('/:id', authMiddleware, async (req, res) => {
  const { data, error } = await supabase
    .from('prescriptions')
    .select('*')
    .eq('id', req.params.id)
    .single();

  if (error || !data) {
    throw new AppError(404, 'Prescription not found');
  }

  res.json(data);
});

// Get prescriptions by patient ID
router.get('/patient/:patientId', authMiddleware, async (req, res) => {
  const { data, error } = await supabase
    .from('prescriptions')
    .select('*')
    .eq('patient_id', req.params.patientId)
    .order('prescription_date', { ascending: false });

  if (error) {
    throw new AppError(400, error.message);
  }

  res.json(data);
});

// Get prescriptions by visit ID
router.get('/visit/:visitId', authMiddleware, async (req, res) => {
  const { data, error } = await supabase
    .from('prescriptions')
    .select('*')
    .eq('visit_id', req.params.visitId)
    .order('prescription_date', { ascending: false });

  if (error) {
    throw new AppError(400, error.message);
  }

  res.json(data);
});

// Update prescription
router.put('/:id', authMiddleware, async (req, res) => {
  const { data, error } = await supabase
    .from('prescriptions')
    .update({
      patient_id: req.body.patientId,
      visit_id: req.body.visitId,
      prescription_date: req.body.prescriptionDate,
      diagnosis: req.body.diagnosis,
      medications: req.body.medications,
      notes: req.body.notes,
      updated_at: new Date().toISOString(),
    })
    .eq('id', req.params.id)
    .select()
    .single();

  if (error || !data) {
    throw new AppError(400, error?.message || 'Failed to update prescription');
  }

  res.json(data);
});

// Delete prescription
router.delete('/:id', authMiddleware, async (req, res) => {
  const { error } = await supabase
    .from('prescriptions')
    .delete()
    .eq('id', req.params.id);

  if (error) {
    throw new AppError(400, error.message);
  }

  res.json({ message: 'Prescription deleted' });
});

export default router;
