export const backend = {
  async getSyncQueue() {
    return [];
  },

  async bulkUpsertPatients(data: any[]) {
    console.log("Mock ICP sync:", data);
    return true;
  },
};
