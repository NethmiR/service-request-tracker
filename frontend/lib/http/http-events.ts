// Small typed publisher that lets the HTTP layer announce session events.
// The auth context subscribes to these events to navigate, since interceptors cannot use the router.

export enum HttpEvent {
  Unauthenticated = "unauthenticated",
  PasswordChangeRequired = "password-change-required",
}

type HttpEventListener = () => void;

const listenersByEvent = new Map<HttpEvent, Set<HttpEventListener>>();

function subscribe(event: HttpEvent, listener: HttpEventListener): () => void {
  const listeners = listenersByEvent.get(event) ?? new Set<HttpEventListener>();
  listeners.add(listener);
  listenersByEvent.set(event, listeners);

  return () => {
    listeners.delete(listener);
  };
}

function publish(event: HttpEvent): void {
  listenersByEvent.get(event)?.forEach((listener) => listener());
}

export const httpEvents = Object.freeze({ subscribe, publish });
