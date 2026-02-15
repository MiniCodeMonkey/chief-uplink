import type { InertiaLinkProps } from '@inertiajs/vue3';
import type { LucideIcon } from 'lucide-vue-next';
import type { StatusDotState } from '@/components/ui/status-dot/StatusDot.vue';

export type BreadcrumbItem = {
    title: string;
    href?: string;
};

export type NavItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon;
    isActive?: boolean;
};

export type DeviceSummary = {
    id: number;
    device_name: string;
    os: string | null;
    arch: string | null;
    chief_version: string | null;
    is_online: boolean;
    last_connected_at: string | null;
    connection_status: StatusDotState;
};

export type ProjectSummary = {
    id: number;
    device_authorization_id: number;
    project_slug: string;
    project_name: string;
    status: string;
    git_branch: string | null;
    current_prd_name: string | null;
    stories_completed: number | null;
    stories_total: number | null;
};

export type ProjectTab = {
    title: string;
    href: string;
    icon?: LucideIcon;
    slug: string;
};
