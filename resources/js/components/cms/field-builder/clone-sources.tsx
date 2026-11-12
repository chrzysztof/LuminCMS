import { createContext, useContext } from 'react';

export type CloneSource = {
    key: string;
    title: string;
    fields: { name: string; label: string }[];
};

/** Grupy pól, które można sklonować (bez edytowanej grupy); dostarcza strona edycji grupy. */
export const CloneSourcesContext = createContext<CloneSource[]>([]);

export const useCloneSources = () => useContext(CloneSourcesContext);
