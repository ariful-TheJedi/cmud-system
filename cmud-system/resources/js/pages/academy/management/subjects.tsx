import CatalogPage, { type ManagementPageProps } from './catalog-page';

export default function SubjectsPage(props: ManagementPageProps) {
    return <CatalogPage section="subjects" {...props} />;
}
