import CatalogPage, { type ManagementPageProps } from './catalog-page';

export default function TeachersPage(props: ManagementPageProps) {
    return <CatalogPage section="teachers" {...props} />;
}
