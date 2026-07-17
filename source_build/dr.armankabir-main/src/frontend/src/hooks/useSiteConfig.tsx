import { useCallback, useState } from "react";
import { landingService } from "../services/landing";
import type { SiteConfig } from "../services/landing";

export interface SocialLink {
  label: string;
  url: string;
  icon: string;
}

export interface EmergencyContact {
  doctorName: string;
  whatsappNumber: string;
  prefilledMessage: string;
}

export interface HeroSection {
  taglineEn: string;
  taglineBn: string;
  subheadingEn: string;
  subheadingBn: string;
  heroTaglineEn?: string;
  heroTaglineBn?: string;
  heroDescriptionEn?: string;
  heroDescriptionBn?: string;
  cta1Label: string;
  cta2Label: string;
}

export interface AboutSection {
  visible: boolean;
  clinicNameEn: string;
  clinicNameBn: string;
  descriptionEn: string;
  descriptionBn: string;
  yearsExperience: number;
  patientCount: string;
  doctorCount: number;
  specialties: string[];
  affiliations: string[];
}

export interface FooterSection {
  addressEn: string;
  addressBn: string;
  phone: string;
  email: string;
  openingHours: string;
  copyrightText: string;
  socialLinks: SocialLink[];
}

export const DEFAULT_SITE_CONFIG: SiteConfig = {
  heroSection: {
    taglineEn: "Dr. Arman Kabir's Care",
    taglineBn: "ডা. আরমান কবিরের চেম্বার",
    subheadingEn: "Advanced Healthcare With a Human Touch",
    subheadingBn: "মানবিক স্পর্শে উন্নত স্বাস্থ্যসেবা",
    heroTaglineEn: "Healing with Trust and Compassion",
    heroTaglineBn: "বিশ্বাস ও সহানুভূতির সাথে নিরাময়",
    heroDescriptionEn:
      "Expert diagnosis, compassionate treatment, and trusted care for every stage of life.",
    heroDescriptionBn:
      "জীবনের প্রতিটি পর্যায়ে বিশেষজ্ঞ রোগ নির্ণয়, সহানুভূতিশীল চিকিৎসা ও বিশ্বস্ত সেবা।",
    cta1Label: "Book Appointment",
    cta2Label: "Emergency",
  },
  aboutSection: {
    visible: true,
    clinicNameEn: "Dr. Arman Kabir's Care",
    clinicNameBn: "ডা. আরমান কবিরের চেম্বার",
    descriptionEn:
      "Comprehensive patient management and medical education serving patients and students across Bangladesh.",
    descriptionBn:
      "বাংলাদেশ জুড়ে রোগী ও শিক্ষার্থীদের জন্য পূর্ণাঙ্গ রোগী ব্যবস্থাপনা ও চিকিৎসা শিক্ষা।",
    yearsExperience: 10,
    patientCount: "500+",
    doctorCount: 2,
    specialties: [
      "Internal Medicine",
      "Respiratory Medicine",
      "Diabetes & Endocrinology",
      "General Practice",
    ],
    affiliations: [
      "BSMMU",
      "DMCH",
      "Dhaka Medical College",
      "National Institute of Diseases of Chest & Hospital",
    ],
  },
  footerSection: {
    addressEn: "Dhaka, Bangladesh",
    addressBn: "ঢাকা, বাংলাদেশ",
    phone: "+880-1751-959262",
    email: "dr.armankabir011@gmail.com",
    openingHours: "Sat–Thu: 9 AM – 8 PM",
    copyrightText: "Dr. Arman Kabir's Care. All rights reserved.",
    socialLinks: [],
  },
  emergencyContacts: [
    {
      doctorName: "Dr. Arman Kabir",
      whatsappNumber: "8801751959262",
      prefilledMessage: "Hello Dr. Arman, I need an emergency consultation.",
    },
    {
      doctorName: "Dr. Samia Shikder",
      whatsappNumber: "880195721221",
      prefilledMessage: "Hello Dr. Samia, I need an emergency consultation.",
    },
  ],
};

export function useSiteConfig() {
  const [config, setConfig] = useState<SiteConfig>(DEFAULT_SITE_CONFIG);
  const [loading, setLoading] = useState(true);

  // Load config from server on mount
  useCallback(async () => {
    try {
      const serverConfig = await landingService.getConfig();
      if (serverConfig) {
        setConfig(serverConfig);
      }
    } catch {
      // Fall back to default
    } finally {
      setLoading(false);
    }
  }, []);

  const persistConfig = useCallback(async (next: SiteConfig) => {
    try {
      await landingService.saveConfig(next);
    } catch {
      // Save failed — config still updated locally
    }
  }, []);

  const updateHero = useCallback((hero: Partial<HeroSection>) => {
    setConfig((prev) => {
      const next = { ...prev, heroSection: { ...prev.heroSection, ...hero } };
      persistConfig(next);
      return next;
    });
  }, [persistConfig]);

  const updateAbout = useCallback((about: Partial<AboutSection>) => {
    setConfig((prev) => {
      const next = { ...prev, aboutSection: { ...prev.aboutSection, ...about } };
      persistConfig(next);
      return next;
    });
  }, [persistConfig]);

  const updateFooter = useCallback((footer: Partial<FooterSection>) => {
    setConfig((prev) => {
      const next = { ...prev, footerSection: { ...prev.footerSection, ...footer } };
      persistConfig(next);
      return next;
    });
  }, [persistConfig]);

  const updateEmergencyContacts = useCallback(
    (contacts: EmergencyContact[]) => {
      setConfig((prev) => {
        const next = { ...prev, emergencyContacts: contacts };
        persistConfig(next);
        return next;
      });
    },
    [persistConfig],
  );

  const resetSection = useCallback((section: keyof SiteConfig) => {
    setConfig((prev) => {
      const next = { ...prev, [section]: DEFAULT_SITE_CONFIG[section] };
      persistConfig(next);
      return next;
    });
  }, [persistConfig]);

  return {
    config,
    loading,
    updateHero,
    updateAbout,
    updateFooter,
    updateEmergencyContacts,
    resetSection,
  };
}
