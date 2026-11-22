import CatalogPage, { type ManagementPageProps } from './catalog-page';

export default function CoursesPage(props: ManagementPageProps) {
    return <CatalogPage section="courses" {...props} />;
}
