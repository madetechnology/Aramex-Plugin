/**
 * External dependencies
 */
import { addFilter } from '@wordpress/hooks';
import { __ } from '@wordpress/i18n';
import { Dropdown, TextControl } from '@wordpress/components';
import * as Woo from '@woocommerce/components';
import { Fragment, useState } from '@wordpress/element';

/**
 * Internal dependencies
 */
import './index.scss';

const MyExamplePage = () => {
	// State to hold the value of the text input
	const [ freeText, setFreeText ] = useState( '' );

	return (
		<Fragment>
			<Woo.Section component="article">
				<Woo.SectionHeader
					title={ __( 'Free Text Field', 'aramex-shipping-aunz' ) }
				/>
				<TextControl
					label={ __( 'Your Input', 'aramex-shipping-aunz' ) }
					placeholder={ __( 'Type something...', 'aramex-shipping-aunz' ) }
					value={ freeText } // Bind the state to the value
					onChange={ ( value ) => setFreeText( value ) } // Update state on change
					help={ __( 'Enter any text here.', 'aramex-shipping-aunz' ) }
				/>
			</Woo.Section>

			<Woo.Section component="article">
				<Woo.SectionHeader
					title={ __( 'Search', 'aramex-shipping-aunz' ) }
				/>
				<Woo.Search
					type="products"
					placeholder="Search for something"
					selected={ [] }
					onChange={ ( items ) => setInlineSelect( items ) }
					inlineTags
				/>
			</Woo.Section>

			<Woo.Section component="article">
				<Woo.SectionHeader
					title={ __( 'Dropdown', 'aramex-shipping-aunz' ) }
				/>
				<Dropdown
					renderToggle={ ( { isOpen, onToggle } ) => (
						<Woo.DropdownButton
							onClick={ onToggle }
							isOpen={ isOpen }
							labels={ [ 'Dropdown' ] }
						/>
					) }
					renderContent={ () => <p>Dropdown content here</p> }
				/>
			</Woo.Section>

			<Woo.Section component="article">
				<Woo.SectionHeader
					title={ __( 'Pill shaped container', 'aramex-shipping-aunz' ) }
				/>
				<Woo.Pill className={ 'pill' }>
					{ __( 'Pill Shape Container', 'aramex-shipping-aunz' ) }
				</Woo.Pill>
			</Woo.Section>

			<Woo.Section component="article">
				<Woo.SectionHeader
					title={ __( 'Spinner', 'aramex-shipping-aunz' ) }
				/>
				<Woo.H>I am a spinner!</Woo.H>
				<Woo.Spinner />
			</Woo.Section>

			<Woo.Section component="article">
				<Woo.SectionHeader
					title={ __( 'Datepicker', 'aramex-shipping-aunz' ) }
				/>
				<Woo.DatePicker
					text={ __( 'I am a datepicker!', 'aramex-shipping-aunz' ) }
					dateFormat={ 'MM/DD/YYYY' }
				/>
			</Woo.Section>
		</Fragment>
	);
};

addFilter(
	'woocommerce_admin_pages_list',
	'aramex-shipping-aunz',
	( pages ) => {
		pages.push( {
			container: MyExamplePage,
			path: '/aramex-shipping-aunz',
			breadcrumbs: [
				__( 'Aramex Shipping Aunz', 'aramex-shipping-aunz' ),
			],
			navArgs: {
				id: 'aramex_shipping_aunz',
			},
		} );

		return pages;
	}
);