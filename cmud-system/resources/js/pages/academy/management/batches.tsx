import CatalogPage, { type ManagementPageProps } from './catalog-page';

export default function BatchesPage(props: ManagementPageProps) {
    return <CatalogPage section="batches" {...props} />;
}
