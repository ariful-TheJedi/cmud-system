import { createInertiaApp } from '@inertiajs/react';
import NotFound from './pages/errors/404';
import Dashboard from './pages/dashboard/index';
import SecuritySettings from './pages/security/settings/security';
import SecurityProfile from './pages/security/settings/profile';
import AppearanceSettings from './pages/security/settings/appearance';
import Students from './pages/academy/students';
import StudentAdmission from './pages/academy/student-admission';
import ExamControl from './pages/academy/students/exam-control';
import Certificates from './pages/academy/certificates';
import StudentBill from './pages/billings/student-bill';
import InstituteBilling from './pages/billings/institute-billing';
import InstituteOtherBill from './pages/billings/institute-other-bill';
import StudentRevenue from './pages/finance/student-revenue';
import InstituteExpenditure from './pages/finance/institute-expenditure';
import CoursesPage from './pages/academy/management/courses';
import BatchesPage from './pages/academy/management/batches';
import SubjectsPage from './pages/academy/management/subjects';
import TeachersPage from './pages/academy/management/teachers';
import InstituteSettings from './pages/settings/institute';
import ClassRoutine from './pages/academy/class-routine';
import UserControl from './pages/settings/user-control/index';
import FinanceControl from './pages/settings/finance-control';
import StuffPage from './pages/stuff';
import Login from './pages/auth/login';
import Inventory from './pages/inventory/index';

const appName = import.meta.env.VITE_APP_NAME || 'CMUD System';
const pages = {
    stuff: StuffPage,
    'auth/login': Login,
    'errors/404': NotFound,
    dashboard: Dashboard,
    'security/settings/security': SecuritySettings,
    'security/settings/profile': SecurityProfile,
    'security/settings/appearance': AppearanceSettings,
    'academy/students': Students,
    'academy/student-admission': StudentAdmission,
    'academy/students/exam-control': ExamControl,
    'academy/certificates': Certificates,
    'finance/student-revenue': StudentRevenue,
    'finance/institute-expenditure': InstituteExpenditure,
    'billings/student-bill': StudentBill,
    'billings/institute-billing': InstituteBilling,
    'billings/institute-other-bill': InstituteOtherBill,
    'academy/management/courses': CoursesPage,
    'academy/management/batches': BatchesPage,
    'academy/management/subjects': SubjectsPage,
    'academy/management/teachers': TeachersPage,
    'settings/institute': InstituteSettings,
    'academy/class-routine': ClassRoutine,
    'settings/user-control/index': UserControl,
    'settings/finance-control': FinanceControl,
    'inventory/index': Inventory,
};

void createInertiaApp({
    resolve: async (name) => {
        const page = pages[name as keyof typeof pages];

        if (!page) {
            throw new Error(`Inertia page "${name}" is not registered.`);
        }

        return page;
    },
    title: (title) => (title ? `${title} - ${appName}` : appName),
    strictMode: true,
    progress: {
        color: '#4B5563',
    },
});
