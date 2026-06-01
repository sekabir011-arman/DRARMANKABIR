import { Router } from 'express';
import { supabase } from '../db/supabase';
import { authMiddleware } from '../middleware/auth';
import { AppError } from '../middleware/errorHandler';
import { Patient } from '../types';
import { v4 as uuid } from 'uuid';

const router = Router();

// Create patient
router.post('/', authMiddleware, async (req, res) => {
  const patient: Patient = {
    id: uuid(),
    ...req.body,
    createdAt: new Date().toISOString(),
    updatedAt: new Date().toISOString(),
  };

  const { data, error } = await supabase
    .from('patients')
    .insert([{
      id: patient.id,
      full_name: patient.fullName,
      name_bn: patient.nameBn,
      date_of_birth: patient.dateOfBirth,
      gender: patient.gender,
      phone: patient.phone,
      email: patient.email,
      address: patient.address,
      blood_group: patient.bloodGroup,
      weight: patient.weight,
      height: patient.height,
      allergies: patient.allergies,
      chronic_conditions: patient.chronicConditions,
      past_surgical_history: patient.pastSurgicalHistory,
      patient_type: patient.patientType,
      consultant_email: patient.consultantEmail,
      consultant_name: patient.consultantName,
    }])
    .select();

  if (error) {
    throw new AppError(400, error.message);
  }

  res.status(201).json(data?.[0]);
});

// Get all patients
router.get('/', authMiddleware, async (req, res) => {
  const { data, error } = await supabase
    .from('patients')
    .select('*')
    .order('created_at', { ascending: false });

  if (error) {
    throw new AppError(400, error.message);
  }

  res.json(data);
});

// Get patient by ID
router.get('/:id', authMiddleware, async (req, res) => {
  const { data, error } = await supabase
    .from('patients')
    .select('*')
    .eq('id', req.params.id)
    .single();

  if (error || !data) {
    throw new AppError(404, 'Patient not found');
  }

  res.json(data);
});

// Update patient
router.put('/:id', authMiddleware, async (req, res) => {
  const { data, error } = await supabase
    .from('patients')
    .update({
      full_name: req.body.fullName,
      name_bn: req.body.nameBn,
      date_of_birth: req.body.dateOfBirth,
      gender: req.body.gender,
      phone: req.body.phone,
      email: req.body.email,
      address: req.body.address,
      blood_group: req.body.bloodGroup,
      weight: req.body.weight,
      height: req.body.height,
      allergies: req.body.allergies,
      chronic_conditions: req.body.chronicConditions,
      past_surgical_history: req.body.pastSurgicalHistory,
      patient_type: req.body.patientType,
      consultant_email: req.body.consultantEmail,
      consultant_name: req.body.consultantName,
      updated_at: new Date().toISOString(),
    })
    .eq('id', req.params.id)
    .select()
    .single();

  if (error || !data) {
    throw new AppError(400, error?.message || 'Failed to update patient');
  }

  res.json(data);
});

// Delete patient
router.delete('/:id', authMiddleware, async (req, res) => {
  const { error } = await supabase
    .from('patients')
    .delete()
    .eq('id', req.params.id);

  if (error) {
    throw new AppError(400, error.message);
  }

  res.json({ message: 'Patient deleted' });
});

// Get patients modified since timestamp
router.get('/sync/since/:timestamp', authMiddleware, async (req, res) => {
  const { data, error } = await supabase
    .from('patients')
    .select('*')
    .gte('updated_at', new Date(parseInt(req.params.timestamp)).toISOString())
    .order('updated_at', { ascending: false });

  if (error) {
    throw new AppError(400, error.message);
  }

  res.json(data);
});

export default router;
