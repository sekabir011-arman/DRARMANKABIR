import {
  ref,
  uploadBytes,
  deleteObject,
  getDownloadURL,
  UploadMetadata,
  StorageReference,
} from 'firebase/storage';
import { storage } from '@/lib/firebase';

export class FirebaseStorageService {
  constructor(private storageRef: any) {}

  async uploadFile(
    path: string,
    file: Blob | File,
    metadata?: UploadMetadata
  ): Promise<string> {
    if (!this.storageRef) {
      throw new Error('Firebase Storage not initialized');
    }

    const fileRef = ref(this.storageRef, path);
    const snapshot = await uploadBytes(fileRef, file, metadata);
    return getDownloadURL(snapshot.ref);
  }

  async deleteFile(path: string): Promise<void> {
    if (!this.storageRef) {
      throw new Error('Firebase Storage not initialized');
    }

    const fileRef = ref(this.storageRef, path);
    await deleteObject(fileRef);
  }

  async getDownloadUrl(path: string): Promise<string> {
    if (!this.storageRef) {
      throw new Error('Firebase Storage not initialized');
    }

    const fileRef = ref(this.storageRef, path);
    return getDownloadURL(fileRef);
  }

  async downloadFile(path: string): Promise<Blob> {
    const url = await this.getDownloadUrl(path);
    const response = await fetch(url);
    return response.blob();
  }
}

// Export singleton instance
export const firebaseStorageService = new FirebaseStorageService(storage);
