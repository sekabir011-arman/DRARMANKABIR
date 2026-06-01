import { Request, Response, NextFunction } from 'express';
import logger from '../utils/logger';

export class AppError extends Error {
  constructor(
    public statusCode: number,
    message: string,
  ) {
    super(message);
  }
}

export const errorHandler = (error: Error, req: Request, res: Response, next: NextFunction) => {
  logger.error('Error:', error);

  if (error instanceof AppError) {
    return res.status(error.statusCode).json({ error: error.message });
  }

  res.status(500).json({ error: 'Internal server error' });
};
