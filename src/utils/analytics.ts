// Google Analytics Configuration
export const GA_TRACKING_ID = 'G-T7SV4CRBYX';

// Google Tag Manager Configuration
export const GTM_ID = 'G-T7SV4CRBYX';

// Analytics event tracking
export const trackEvent = (action: string, category: string, label?: string, value?: number) => {
  if (typeof window !== 'undefined' && (window as any).gtag) {
    (window as any).gtag('event', action, {
      event_category: category,
      event_label: label,
      value: value,
    });
  }
};

// Page view tracking
export const trackPageView = (url: string) => {
  if (typeof window !== 'undefined' && (window as any).gtag) {
    (window as any).gtag('config', GA_TRACKING_ID, {
      page_path: url,
    });
  }
};

// Form submission tracking
export const trackFormSubmission = (formType: string) => {
  trackEvent('form_submit', 'engagement', formType);
};

// Contact form tracking
export const trackContactForm = () => {
  trackEvent('contact_form_submit', 'engagement', 'contact_form');
};

// Service interaction tracking
export const trackServiceClick = (serviceName: string) => {
  trackEvent('service_click', 'engagement', serviceName);
}; 